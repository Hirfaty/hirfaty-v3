<?php

namespace App\Services\Cathedis;

use App\Models\CathedisCity;
use App\Models\CathedisSector;
use App\Models\CathedisShipment;
use App\Models\City;
use App\Models\Order;
use App\Notifications\Admin\CathedisDeliveryIssue;
use App\Services\AdminNotificationService;
use App\Services\OrderStatusService;
use App\Services\ProductShippingService;
use Database\Seeders\CathedisDeliveryRateSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A purchase shipped with Cathedis: the parcel created from the desk, its
 * slip, the pickup request, and Cathedis's statuses carried back onto the
 * orders.
 *
 * One parcel per purchase — the desk gathers every workshop's piece and hands
 * Cathedis a single parcel, so its LD code becomes every order's tracking
 * number. The desk sees the parcel before it is sent: draft() fills every
 * field from the orders, the desk corrects what it needs to (a typo in the
 * phone, the Cathedis city of a locality Cathedis spells differently), and
 * create() checks the result again before calling Cathedis.
 *
 * Creating it moves nothing: the purchase turns "shipped" when Cathedis has
 * collected it, "delivered" when Cathedis has delivered it, and the buyer is
 * emailed at both steps as if the desk had clicked. A refusal or cancellation
 * at the door alerts the desk instead of cancelling on its own — stock and
 * refunds stay the desk's decision.
 */
class CathedisShipmentService
{
    /** Cathedis's first status, given to every new delivery. */
    public const AWAITING_PICKUP = 'En Attente Ramassage';

    /** Cathedis's weight brackets. The last needs the weight itself. */
    public const RANGES = [
        'Entre 1.2 Kg et 5 Kg',
        'Entre 6Kg et 10Kg',
        'Entre 11Kg et 29Kg',
        self::RANGE_HEAVY,
    ];

    public const RANGE_HEAVY = 'Plus de 30Kg';

    /** How the courier settles: cash, already paid, or Cathedis's other modes. */
    public const PAYMENT_TYPES = ['ESPECES', 'PAYE', 'CHEQUE', 'TPE', 'EFFET DE COMMERCE'];

    /** The parcel is back with Hirfaty: nothing more will happen to it. */
    private const RETURNED = 'Colis Retourné';

    /**
     * What the buyer reads on their tracking page when the parcel reaches a
     * status. A status left out adds no line — its own email (shipped,
     * delivered) already says it, or there is nothing the buyer can act on.
     */
    private const BUYER_NOTES = [
        'Collecté' => 'Colis remis à Cathedis, notre transporteur.',
        'Expédié vers hub destination' => 'Colis en route vers votre ville.',
        'Hub CATHEDIS' => 'Colis arrivé au centre de tri Cathedis.',
        'Confirmée Sous RDV' => 'Rendez-vous de livraison confirmé avec le livreur.',
        'Mise En Distribution' => 'Colis en cours de livraison : le livreur va vous appeler.',
        'Attente d\'appel client' => 'Le livreur attend votre appel pour convenir de la livraison.',
        'Client En Voyage' => 'Livraison reportée à votre retour.',
    ];

    /** Any other on-hold status: the courier could not reach the buyer. */
    private const STANDBY_NOTE = 'Le livreur n\'a pas pu vous joindre ; il vous rappellera.';

    /** A piece whose product has no weight on file counts as this much. */
    private const DEFAULT_PIECE_KG = 1.0;

    /** Cathedis documents `declaredValue` above this for a fragile parcel. */
    public const FRAGILE_MIN_VALUE = 250;

    /** Parcels not polled any more, delivered or not, after this long. */
    private const POLL_DAYS = 90;

    public function __construct(
        private readonly CathedisClient $client,
        private readonly OrderStatusService $statuses,
        private readonly AdminNotificationService $notifications,
        private readonly ProductShippingService $shipping,
    ) {}

    /**
     * What the order page's Cathedis panel shows: the parcel once it exists;
     * before that, the draft the desk edits, what to check in it, and what —
     * if anything — stops it being sent. Null for a purchase that does not
     * ship with Cathedis.
     *
     * @param  Collection<int, Order>  $orders  Every order of the purchase.
     * @return array<string, mixed>|null
     */
    public function panel(Collection $orders): ?array
    {
        $first = $orders->first();
        $shipment = $first ? $this->shipmentFor($first) : null;

        if (! $shipment && (! $first || ! $this->shipsWithCathedis($first))) {
            return null;
        }

        $blocked = $shipment ? null : $this->blocker($orders);

        return [
            'shipment' => $shipment ? [
                'code' => $shipment->code,
                'delivery_status' => $shipment->delivery_status,
                'delivery_status_type' => $shipment->delivery_status_type,
                'payment_status' => $shipment->payment_status,
                'amount' => (float) $shipment->amount,
                'payment_type' => $shipment->payment_type,
                'range_weight' => $shipment->range_weight,
                'pickup_requested_at' => $shipment->pickup_requested_at?->toIso8601String(),
                'synced_at' => $shipment->synced_at?->toIso8601String(),
                'closed' => $shipment->closed_at !== null,
            ] : null,
            'blocked' => $blocked,
            'draft' => $shipment || $blocked ? null : $this->draft($orders),
            'warnings' => $shipment || $blocked ? [] : $this->warnings($orders),
            'ranges' => self::RANGES,
            'payment_types' => self::PAYMENT_TYPES,
        ];
    }

    /**
     * The parcel as Hirfaty would send it, field by field, for the desk to
     * check and correct before it goes.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    public function draft(Collection $orders): array
    {
        $live = $this->live($orders);
        $first = $live->first();
        $paidOnline = $first->paysOnline();
        $city = $first->city;
        $cathedisCity = $city?->cathedisCity;

        [$recipient, $address] = $this->recipientAndAddress($first);
        $kg = $this->weightKg($live);

        return [
            'recipient' => Str::limit($recipient, 255, ''),
            'address' => Str::limit($address, 255, ''),
            // As Cathedis wants it when it reads as a Moroccan number, else as typed.
            'phone' => self::phone($first->shipping_phone) ?? (string) $first->shipping_phone,
            // Our locality, for the desk to see which Cathedis city it became.
            'city_name' => $city?->name,
            'cathedis_city_id' => $cathedisCity?->isServed() ? $cathedisCity->id : null,
            'sector' => $cathedisCity?->isServed() ? $this->defaultSector($cathedisCity, $city->cathedisSector) : null,
            // Paid online: the courier collects nothing.
            'amount' => $paidOnline ? 0.0 : round((float) $live->sum(fn (Order $o) => (float) $o->total_amount + (float) $o->delivery_fee), 2),
            'payment_type' => $paidOnline ? 'PAYE' : 'ESPECES',
            'subject' => $this->subject($live),
            'range_weight' => $this->rangeFor($kg),
            'weight' => $kg >= 30 ? (int) ceil($kg) : null,
            'fragile' => false,
            'declared_value' => round((float) $live->sum(fn (Order $o) => (float) $o->total_amount), 2),
            'allow_opening' => false,
            'package_count' => 1,
            'comment' => Str::limit(trim((string) $first->notes), 255, ''),
            // A locality with no Cathedis city yet: keep the one the desk picks.
            'remember_city' => $cathedisCity === null,
        ];
    }

    /**
     * Every city Cathedis delivers to with its sectors — what the dialog's
     * city and sector lists offer. A name Cathedis lists twice (MIDELT,
     * MIRLEFT) carries its id so the two can be told apart.
     *
     * @return list<array{id: int, name: string, sectors: list<array{id: int, name: string}>, default_sector: string}>
     */
    public function cityOptions(): array
    {
        $cities = CathedisCity::with('sectors')
            ->where('delivers', true)
            ->where('archived', false)
            ->orderBy('name')
            ->get();

        $twice = $cities->countBy(fn (CathedisCity $c) => Str::lower($c->name))->filter(fn (int $n) => $n > 1);

        return $cities->map(fn (CathedisCity $c) => [
            'id' => $c->id,
            'name' => $twice->has(Str::lower($c->name)) ? "{$c->name} (#{$c->id})" : $c->name,
            'sectors' => $c->sectors->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (CathedisSector $s) => ['id' => $s->id, 'name' => $s->name])
                ->values()->all(),
            'default_sector' => $this->defaultSector($c),
        ])->values()->all();
    }

    /**
     * Create the purchase's parcel at Cathedis from the fields the desk
     * checked, and give every order its code.
     *
     * Locked per purchase, so a double click cannot create two parcels.
     *
     * @param  array<string, mixed>  $fields  The dialog's fields, shaped like draft().
     *
     * @throws CathedisException When the purchase cannot ship, a field is wrong, or Cathedis refuses it.
     */
    public function create(Order $order, array $fields): CathedisShipment
    {
        $reference = CathedisShipment::referenceFor($order);

        return Cache::lock("cathedis.ship.{$reference}", 30)->block(10, function () use ($order, $fields, $reference) {
            if ($existing = CathedisShipment::where('purchase_reference', $reference)->first()) {
                throw new CathedisException("Un colis Cathedis existe déjà pour cet achat ({$existing->code}).");
            }

            $orders = $order->groupOrders()
                ->with(['buyer', 'deliveryService', 'items.product', 'items.variant', 'city.cathedisCity.sectors', 'city.cathedisSector'])
                ->orderBy('id')
                ->get();

            if ($reason = $this->blocker($orders)) {
                throw new CathedisException($reason);
            }

            $city = CathedisCity::with('sectors')->find($fields['cathedis_city_id'] ?? null);
            $payload = $this->payload($reference, $city, $fields);
            $saved = $this->client->createDelivery($payload);
            $code = self::code((int) $saved['id']);

            $shipment = CathedisShipment::create([
                'purchase_reference' => $reference,
                'delivery_id' => (int) $saved['id'],
                'code' => $code,
                'amount' => $payload['amount'],
                'payment_type' => $payload['paymentType'],
                'range_weight' => $payload['rangeWeight'],
                'delivery_status' => is_string($saved['deliveryStatus'] ?? null) ? $saved['deliveryStatus'] : self::AWAITING_PICKUP,
                'delivery_status_type' => 'PENDING',
                'payment_status' => isset($saved['status']) ? Str::lower((string) $saved['status']) : null,
                'synced_at' => now(),
            ]);

            Order::whereIn('id', $this->live($orders)->pluck('id'))->update(['tracking_number' => $code]);

            // The next parcel to this locality finds its Cathedis city on its own.
            $locality = $this->live($orders)->first()->city;

            if (! empty($fields['remember_city']) && $locality && $locality->cathedis_city_id !== $city->id) {
                $locality->update(['cathedis_city_id' => $city->id, 'cathedis_sector_id' => null]);
            }

            return $shipment;
        });
    }

    /**
     * The purchase's delivery slip as PDF bytes: A5, or a 10×10 cm label.
     *
     * @throws CathedisException
     */
    public function label(Order $order, bool $thermal = false): string
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new CathedisException('Aucun colis Cathedis pour cet achat.');

        return $this->client->label([$shipment->delivery_id], $thermal);
    }

    /**
     * Ask Cathedis to collect every parcel waiting for pickup.
     *
     * @return array{name: string|null, agent: string|null, count: int}
     *
     * @throws CathedisException
     */
    public function requestPickup(): array
    {
        $waiting = CathedisShipment::whereNull('closed_at')
            ->where(fn ($query) => $query->whereNull('delivery_status')->orWhere('delivery_status', self::AWAITING_PICKUP))
            ->get();

        if ($waiting->isEmpty()) {
            throw new CathedisException('Aucun colis en attente de ramassage.');
        }

        $request = $this->client->requestPickup($waiting->pluck('delivery_id')->all());

        CathedisShipment::whereIn('id', $waiting->pluck('id'))->update(['pickup_requested_at' => now()]);

        return [
            'name' => $request['name'] ?? null,
            'agent' => $request['agent']['fullName'] ?? null,
            'count' => (int) ($request['deliveriesCount'] ?? $waiting->count()),
        ];
    }

    /**
     * Read the purchase's parcel from Cathedis now, rather than waiting for
     * the scheduled refresh.
     *
     * @throws CathedisException
     */
    public function refreshFor(Order $order): bool
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new CathedisException('Aucun colis Cathedis pour cet achat.');

        return $this->refresh($shipment);
    }

    /**
     * Refresh every parcel still on its way — what the scheduled command runs.
     *
     * @return array{checked: int, changed: int, failed: int}
     */
    public function refreshOpen(): array
    {
        $result = ['checked' => 0, 'changed' => 0, 'failed' => 0];

        if (! $this->client->isConfigured()) {
            return $result;
        }

        $open = CathedisShipment::whereNull('closed_at')
            ->where('created_at', '>=', now()->subDays(self::POLL_DAYS))
            ->orderBy('id')
            ->get();

        foreach ($open as $shipment) {
            $result['checked']++;

            try {
                $result['changed'] += $this->refresh($shipment) ? 1 : 0;
            } catch (CathedisException $e) {
                $result['failed']++;
                Log::warning("Cathedis refresh failed for {$shipment->code}: ".$e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Read one parcel from Cathedis and, when its status moved, carry the
     * move onto the orders. Returns whether it moved.
     *
     * @throws CathedisException
     */
    public function refresh(CathedisShipment $shipment): bool
    {
        $delivery = $this->client->fetchDelivery($shipment->delivery_id);

        $status = $delivery['deliveryStatus'] ?? null;
        $name = trim((string) (is_array($status) ? ($status['name'] ?? $status['code'] ?? '') : $status));
        $type = is_array($status) ? ($status['type'] ?? null) : null;
        $type = Str::upper((string) ($type ?? $delivery['deliveryStatus.type'] ?? '')) ?: null;

        $moved = $name !== '' && $name !== $shipment->delivery_status;

        $shipment->fill([
            'delivery_status' => $name !== '' ? $name : $shipment->delivery_status,
            'delivery_status_type' => $type ?? $shipment->delivery_status_type,
            'payment_status' => isset($delivery['status']) ? Str::lower((string) $delivery['status']) : $shipment->payment_status,
            'synced_at' => now(),
        ]);

        if ($type === 'SHIPPED' || $name === self::RETURNED) {
            $shipment->closed_at ??= now();
        }

        $shipment->save();

        if ($moved) {
            $this->apply($shipment, $name, $type);
        }

        return $moved;
    }

    /** Cathedis's printed code for a delivery id: 536382 → LD000536382. */
    public static function code(int $deliveryId): string
    {
        return 'LD'.str_pad((string) $deliveryId, 9, '0', STR_PAD_LEFT);
    }

    /**
     * A Moroccan number as Cathedis accepts it (06/07/08/05 + eight digits),
     * from any of the ways a buyer types one; null when it is not one.
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $phone);

        $digits = match (true) {
            str_starts_with($digits, '+212') => '0'.substr($digits, 4),
            str_starts_with($digits, '00212') => '0'.substr($digits, 5),
            str_starts_with($digits, '212') && strlen($digits) === 12 => '0'.substr($digits, 3),
            default => $digits,
        };

        return preg_match('/^0[5-8]\d{8}$/', $digits) ? $digits : null;
    }

    /** Carry a new Cathedis status onto the purchase's orders. */
    private function apply(CathedisShipment $shipment, string $name, ?string $type): void
    {
        $orders = $shipment->orders()->where('status', '!=', 'cancelled')->orderBy('id')->get();

        if ($orders->isEmpty()) {
            return;
        }

        if ($type === 'SHIPPED') {
            $undelivered = $orders->where('status', '!=', 'delivered');

            if ($undelivered->isNotEmpty()) {
                $this->statuses->transitionMany($undelivered->values(), 'delivered', ['tracking_number' => $shipment->code]);
            }

            return;
        }

        if ($type === 'REJECTED') {
            $this->notifications->notifyAdmins(new CathedisDeliveryIssue(
                $shipment->purchase_reference,
                $orders->first()->reference,
                $shipment->code,
                $name,
            ));

            return;
        }

        $note = self::BUYER_NOTES[$name] ?? ($type === 'STANDBY' ? self::STANDBY_NOTE : null);

        // Anything past "awaiting pickup" means Cathedis has the parcel.
        $toShip = $name === self::AWAITING_PICKUP
            ? collect()
            : $orders->whereIn('status', ['pending', 'confirmed']);

        if ($toShip->isNotEmpty()) {
            $this->statuses->transitionMany($toShip->values(), 'shipped', ['tracking_number' => $shipment->code], $note);
        }

        $rest = $orders->whereNotIn('id', $toShip->pluck('id'))->where('status', '!=', 'delivered');

        if ($note !== null && $rest->isNotEmpty()) {
            $this->statuses->addNote($rest->values(), $note);
        }
    }

    /**
     * Why the purchase cannot be handed to Cathedis at all, in the desk's
     * words — null when it can. What the desk can fix in the dialog (the city,
     * the phone) is a warning instead.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function blocker(Collection $orders): ?string
    {
        $live = $this->live($orders);
        $first = $live->first();

        if (! $this->client->isConfigured()) {
            return 'Identifiants Cathedis non configurés (CATHEDIS_USERNAME / CATHEDIS_PASSWORD).';
        }

        if (! $first) {
            return 'Toutes les commandes de cet achat sont annulées.';
        }

        if (! $this->shipsWithCathedis($first)) {
            return 'Cet achat n\'est pas livré par Cathedis.';
        }

        if ($live->contains(fn (Order $o) => $o->status === 'delivered')) {
            return 'Cet achat est déjà livré.';
        }

        if ($first->paysOnline() && $first->payment_status !== 'paid') {
            return 'Le paiement en ligne de cet achat n\'est pas réglé.';
        }

        return null;
    }

    /**
     * What the desk should look at in the draft before sending it.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<string>
     */
    private function warnings(Collection $orders): array
    {
        $first = $this->live($orders)->first();
        $city = $first->city;
        $warnings = [];

        if (! $city) {
            $warnings[] = 'La commande n\'a pas de ville : choisissez la ville Cathedis.';
        } elseif (! $city->cathedisCity) {
            $warnings[] = "« {$city->name} » n'est pas reliée à une ville Cathedis : choisissez-la, elle sera retenue pour les prochains colis.";
        } elseif (! $city->cathedisCity->isServed()) {
            $warnings[] = "Cathedis ne livre pas « {$city->cathedisCity->name} » en ce moment : choisissez une ville proche ou contactez Cathedis.";
        }

        if (self::phone($first->shipping_phone) === null) {
            $warnings[] = "Le numéro « {$first->shipping_phone} » n'est pas accepté par Cathedis (06…, 07…, 05… ou +212…) : corrigez-le.";
        }

        return $warnings;
    }

    /**
     * The delivery Cathedis is asked to create, from the desk's fields, in
     * Cathedis's documented names — every value a string, as its examples
     * send them. Re-checks what the form cannot guarantee.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     *
     * @throws CathedisException
     */
    private function payload(string $reference, ?CathedisCity $city, array $fields): array
    {
        if (! $city || ! $city->isServed()) {
            throw new CathedisException('Choisissez une ville que Cathedis dessert.');
        }

        $sector = trim((string) ($fields['sector'] ?? ''));

        if ($city->sectors->isNotEmpty() && ! $city->sectors->contains('name', $sector)) {
            throw new CathedisException("Le secteur « {$sector} » n'existe pas à {$city->name} chez Cathedis.");
        }

        $phone = self::phone($fields['phone'] ?? null)
            ?? throw new CathedisException('Numéro du destinataire refusé par Cathedis (attendu 06…, 07…, 05… ou +212…).');

        $range = (string) ($fields['range_weight'] ?? '');

        if (! in_array($range, self::RANGES, true)) {
            throw new CathedisException('Tranche de poids inconnue de Cathedis.');
        }

        $weight = (int) ($fields['weight'] ?? 0);

        if ($range === self::RANGE_HEAVY && $weight < 30) {
            throw new CathedisException('Au-delà de 30 kg, Cathedis demande le poids exact (30 kg ou plus).');
        }

        $fragile = (bool) ($fields['fragile'] ?? false);
        $declared = round((float) ($fields['declared_value'] ?? 0), 2);

        if ($fragile && $declared <= self::FRAGILE_MIN_VALUE) {
            throw new CathedisException('Un colis fragile doit être déclaré à plus de '.self::FRAGILE_MIN_VALUE.' MAD.');
        }

        $paymentType = (string) ($fields['payment_type'] ?? 'ESPECES');

        if (! in_array($paymentType, self::PAYMENT_TYPES, true)) {
            throw new CathedisException('Mode de paiement inconnu de Cathedis.');
        }

        return array_merge([
            'recipient' => Str::limit(trim((string) ($fields['recipient'] ?? '')), 255, ''),
            'address' => Str::limit(trim((string) ($fields['address'] ?? '')), 255, ''),
            'phone' => $phone,
            'city' => $city->name,
            'sector' => $sector !== '' ? $sector : 'Autre',
            'amount' => number_format(max(0, (float) ($fields['amount'] ?? 0)), 2, '.', ''),
            'caution' => '0',
            'fragile' => $fragile ? '1' : '0',
            'declaredValue' => $fragile ? number_format($declared, 2, '.', '') : '0',
            'nomOrder' => $reference,
            'comment' => Str::limit(trim((string) ($fields['comment'] ?? '')), 255, ''),
            'rangeWeight' => $range,
            'subject' => Str::limit(trim((string) ($fields['subject'] ?? '')) ?: 'Artisanat', 255, ''),
            'paymentType' => $paymentType,
            'deliveryType' => 'Livraison CRBT',
            'packageCount' => (string) max(1, min(20, (int) ($fields['package_count'] ?? 1))),
            'allowOpening' => ! empty($fields['allow_opening']) ? '1' : '0',
        ], $range === self::RANGE_HEAVY ? ['weight' => (string) $weight] : []);
    }

    /**
     * The shipping line starts with the name typed at checkout
     * (CheckoutDestinationService::withRecipient), the rest is the address.
     *
     * @return array{0: string, 1: string}
     */
    private function recipientAndAddress(Order $order): array
    {
        $line = trim((string) $order->shipping_address);
        $parts = explode(', ', $line, 2);

        if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
            return [trim($parts[0]), trim($parts[1])];
        }

        $buyer = trim(($order->buyer?->first_name ?? '').' '.($order->buyer?->last_name ?? ''));

        return [$buyer !== '' ? $buyer : 'Client Hirfaty', $line];
    }

    /**
     * The sector set on our city when there is one, else the city's "Autre" —
     * Cathedis's documented fallback — else the sector named after the city
     * (some have no "Autre"), else the first, else "Autre" anyway.
     */
    private function defaultSector(CathedisCity $city, ?CathedisSector $chosen = null): string
    {
        if ($chosen && $chosen->cathedis_city_id === $city->id) {
            return $chosen->name;
        }

        $sectors = $city->sectors->sortBy('id');
        $key = fn (string $name) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));

        return ($sectors->first(fn ($s) => $key($s->name) === 'autre')
            ?? $sectors->first(fn ($s) => $key($s->name) === $key($city->name))
            ?? $sectors->first())?->name ?? 'Autre';
    }

    /**
     * The parcel's weight: every unit's recorded weight, a piece with none
     * counting as DEFAULT_PIECE_KG.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function weightKg(Collection $orders): float
    {
        $pieces = $this->shipping->pieces($orders->flatMap->items);

        return (float) collect($pieces)->sum(fn (array $piece) => $piece['weight'] ?? self::DEFAULT_PIECE_KG);
    }

    /** Cathedis's bracket for a weight. */
    private function rangeFor(float $kg): string
    {
        return match (true) {
            $kg <= 5 => self::RANGES[0],
            $kg <= 10 => self::RANGES[1],
            $kg < 30 => self::RANGES[2],
            default => self::RANGE_HEAVY,
        };
    }

    /**
     * "Tajine ×2, Tapis" — what is in the parcel, printed on the slip.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function subject(Collection $orders): string
    {
        $subject = $orders->flatMap->items
            ->map(fn ($item) => ($item->product?->name ?? 'Article').($item->quantity > 1 ? " ×{$item->quantity}" : ''))
            ->implode(', ');

        return Str::limit($subject !== '' ? $subject : 'Artisanat', 255, '');
    }

    /** Whether the purchase was billed with Cathedis at checkout. */
    private function shipsWithCathedis(Order $order): bool
    {
        return $order->deliveryService?->slug === CathedisDeliveryRateSeeder::SLUG;
    }

    private function shipmentFor(Order $order): ?CathedisShipment
    {
        return CathedisShipment::where('purchase_reference', CathedisShipment::referenceFor($order))->first();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    private function live(Collection $orders): Collection
    {
        return $orders->where('status', '!=', 'cancelled')->values();
    }
}
