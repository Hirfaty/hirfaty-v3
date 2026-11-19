<?php

namespace App\Services\DhlExpress;

use App\Models\DeliveryService;
use App\Models\DhlShipment;
use App\Models\DhlShipmentDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\Admin\DhlDeliveryIssue;
use App\Services\AdminNotificationService;
use App\Services\CountryService;
use App\Services\DeliveryFeeService;
use App\Services\OrderStatusService;
use App\Services\ProductShippingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DhlDeliveryRateSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A purchase shipped abroad with DHL Express: the shipment created from the
 * desk (waybill, label, commercial invoice and, if asked, the courier), its
 * checkpoints carried back onto the orders, and the courier booking.
 *
 * One shipment per purchase, like Cathedis: the desk gathers every
 * workshop's piece and hands DHL one shipment, so its waybill becomes every
 * order's tracking number. The desk sees it before it is sent — draft() fills
 * every field from the orders, the desk corrects what it must (the town of an
 * address typed as one line, a customs description, the box) and can have
 * DHL check it all without creating anything (validate()).
 *
 * Creating it moves nothing: the purchase turns "shipped" at DHL's first
 * checkpoint (it has the parcel) and "delivered" at OK, emailing the buyer
 * both times as if the desk had clicked. A bad address, a refusal, unpaid
 * duties or a return alerts the desk instead of cancelling on its own.
 */
class DhlShipmentService
{
    /** What the buyer reads on their tracking page at a checkpoint. */
    private const BUYER_NOTES = [
        'PU' => 'Colis remis à DHL Express, notre transporteur international.',
        'AR' => 'Colis arrivé dans le pays de destination.',
        'CR' => 'Dédouanement terminé.',
        'WC' => 'Colis en cours de livraison par le livreur DHL.',
        'NH' => 'Le livreur DHL ne vous a pas trouvé ; une nouvelle tentative est prévue.',
        'CC' => 'Colis disponible au point DHL : en attente de votre retrait.',
    ];

    /** Checkpoints the desk must act on: bad address, moved, refused, returned, stopped, duties unpaid. */
    private const ISSUES = ['BA', 'CM', 'RD', 'RT', 'SS', 'HP'];

    private const DELIVERED = 'OK';

    private const RETURNED = 'RT';

    /** DHL's Express Worldwide label and commercial invoice templates. */
    private const LABEL_TEMPLATE = 'ECOM26_84_001';

    private const INVOICE_TEMPLATE = 'COMMERCIAL_INVOICE_P_10';

    /** Every price on the order is in dirhams. */
    public const CURRENCY = 'MAD';

    public const INCOTERMS = ['DAP', 'DDP'];

    /** Documents the desk can download, in the order they are offered. */
    public const DOCUMENT_TYPES = ['label', 'invoice', 'waybillDoc', 'pod'];

    /** A box with no dimensions on file. */
    private const DEFAULT_BOX_CM = ['length' => 30.0, 'width' => 20.0, 'height' => 15.0];

    private const POLL_DAYS = 90;

    /** DHL takes a shipping date from today up to ten days ahead. */
    public const MAX_DAYS_AHEAD = 10;

    private const TIMEZONE = 'Africa/Casablanca';

    public function __construct(
        private readonly DhlExpressClient $client,
        private readonly OrderStatusService $statuses,
        private readonly AdminNotificationService $notifications,
        private readonly ProductShippingService $shipping,
        private readonly DeliveryFeeService $deliveryFees,
        private readonly CountryService $countries,
    ) {}

    /**
     * What the order page's DHL panel shows: the shipment once it exists;
     * before that, the draft the desk edits, what to check in it, and what —
     * if anything — stops it being sent. Null for a purchase DHL does not ship.
     *
     * @param  Collection<int, Order>  $orders  Every order of the purchase.
     * @return array<string, mixed>|null
     */
    public function panel(Collection $orders): ?array
    {
        $first = $orders->first();
        $shipment = $first ? $this->shipmentFor($first) : null;

        if (! $shipment && (! $first || ! $this->shipsWithDhl($first))) {
            return null;
        }

        $blocked = $shipment ? null : $this->blocker($orders);

        return [
            'environment' => $this->client->isTest() ? 'test' : 'production',
            'shipment' => $shipment ? $this->present($shipment) : null,
            'blocked' => $blocked,
            'draft' => $shipment || $blocked ? null : $this->draft($orders),
            'warnings' => $shipment || $blocked ? [] : $this->warnings($orders),
            'incoterms' => self::INCOTERMS,
            'max_days_ahead' => self::MAX_DAYS_AHEAD,
            'countries' => $shipment || $blocked ? [] : $this->countryOptions(),
            'pickup_defaults' => [
                'close_time' => (string) config('services.dhl_express.pickup_close_time', '18:00'),
                'location' => (string) config('services.dhl_express.pickup_location', 'reception'),
            ],
        ];
    }

    /**
     * The countries on DHL's card, named in French, for the dialog's list.
     *
     * @return list<array{code: string, name: string}>
     */
    private function countryOptions(): array
    {
        $service = DeliveryService::where('slug', DhlDeliveryRateSeeder::SLUG)->first();

        return collect($service ? $this->deliveryFees->shippableCountryCodes($service) : [])
            ->reject(fn (string $code) => $code === 'MA')
            ->map(fn (string $code) => ['code' => $code, 'name' => $this->countries->name($code, 'fr')])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * The shipment as Hirfaty would send it, for the desk to check.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    public function draft(Collection $orders): array
    {
        $live = $this->live($orders);
        $first = $live->first();
        [$recipient, $line] = $this->recipientAndAddress($first);
        $town = filled($first->shipping_city) ? (string) $first->shipping_city : $first->city?->name;
        $lines = $this->addressLines($line, $first->shipping_postal_code, $town);
        $items = $live->flatMap->items->values();

        $draftItems = $items->map(fn (OrderItem $item) => [
            'description' => $this->customsDescription($item),
            'quantity' => max(1, (int) $item->quantity),
            'price' => round((float) $item->unit_price, 2),
            'hs_code' => $item->product ? $this->shipping->hsCode($item->product) : null,
            'origin_country' => Str::upper((string) ($item->product?->origin_country ?: 'MA')),
            // Per unit, kg.
            'weight' => $this->unitWeightKg($item),
        ])->all();

        $totalKg = collect($draftItems)->sum(fn (array $i) => $i['weight'] * $i['quantity']);

        return [
            'receiver' => [
                'name' => Str::limit($recipient, 255, ''),
                'phone' => (string) $first->shipping_phone,
                'email' => (string) ($first->buyer?->email ?? ''),
                'address_line1' => $lines[0] ?? '',
                'address_line2' => $lines[1] ?? '',
                'address_line3' => $lines[2] ?? '',
                // A Moroccan city is on file; a town abroad is in the typed line.
                // Typed at checkout (or the Moroccan city); older orders abroad have none.
                'city' => (string) ($town ?? ''),
                'postal_code' => (string) ($first->shipping_postal_code ?? ''),
                'country' => (string) ($first->shipping_country ?? ''),
            ],
            'packages' => [[
                'weight' => max(0.1, round($totalKg, 3)),
                ...$this->boxFor($items),
            ]],
            'items' => $draftItems,
            'incoterm' => 'DAP',
            'shipping_date' => $this->defaultShippingDate(),
            'pickup' => [
                'requested' => true,
                'close_time' => (string) config('services.dhl_express.pickup_close_time', '18:00'),
                'location' => (string) config('services.dhl_express.pickup_location', 'reception'),
            ],
            'notify_buyer' => true,
        ];
    }

    /**
     * Have DHL check the shipment without creating it: every refusal it
     * would make, field by field.
     *
     * @param  array<string, mixed>  $fields  The dialog's fields, shaped like draft().
     * @return list<string> DHL's warnings — empty when it accepts everything.
     *
     * @throws DhlExpressException
     */
    public function validate(Order $order, array $fields): array
    {
        $orders = $this->purchase($order);

        if ($reason = $this->blocker($orders)) {
            throw new DhlExpressException($reason);
        }

        $answer = $this->client->createShipment($this->payload($orders, $fields), validateOnly: true);

        return array_values(array_filter((array) ($answer['warnings'] ?? []), 'is_string'));
    }

    /**
     * Create the purchase's shipment at DHL from the fields the desk checked,
     * keep its documents, and give every order its waybill number.
     *
     * Locked per purchase, so a double click cannot create two shipments.
     *
     * @param  array<string, mixed>  $fields  The dialog's fields, shaped like draft().
     *
     * @throws DhlExpressException
     */
    public function create(Order $order, array $fields): DhlShipment
    {
        $reference = DhlShipment::referenceFor($order);

        return Cache::lock("dhl.ship.{$reference}", 60)->block(15, function () use ($order, $fields, $reference) {
            if ($existing = DhlShipment::where('purchase_reference', $reference)->first()) {
                throw new DhlExpressException("Une expédition DHL existe déjà pour cet achat ({$existing->tracking_number}).");
            }

            $orders = $this->purchase($order);

            if ($reason = $this->blocker($orders)) {
                throw new DhlExpressException($reason);
            }

            $payload = $this->payload($orders, $fields);
            $answer = $this->client->createShipment($payload);
            $awb = (string) ($answer['shipmentTrackingNumber'] ?? '');

            if ($awb === '') {
                throw new DhlExpressException('DHL a répondu sans numéro de suivi.');
            }

            $charge = collect((array) ($answer['shipmentCharges'] ?? []))
                ->sortBy(fn ($c) => ($c['currencyType'] ?? '') === 'BILLC' ? 0 : 1)
                ->first();
            $estimated = $answer['estimatedDeliveryDate']['estimatedDeliveryDate'] ?? null;
            $pickup = $answer['dispatchConfirmationNumber'] ?? null;
            $live = $this->live($orders);
            $receiver = (array) $fields['receiver'];

            return DB::transaction(function () use ($answer, $awb, $charge, $estimated, $pickup, $live, $payload, $reference, $receiver) {
                $shipment = DhlShipment::create([
                    'purchase_reference' => $reference,
                    'tracking_number' => $awb,
                    'piece_tracking_numbers' => collect((array) ($answer['packages'] ?? []))
                        ->pluck('trackingNumber')->filter()->values()->all(),
                    'product_code' => $payload['productCode'],
                    'incoterm' => $payload['content']['incoterm'],
                    'planned_shipping_date' => substr($payload['plannedShippingDateAndTime'], 0, 10),
                    'packages' => array_map(fn (array $p) => [
                        'weight' => $p['weight'],
                        ...$p['dimensions'],
                    ], $payload['content']['packages']),
                    'declared_value' => $payload['content']['declaredValue'],
                    'charge' => is_array($charge) && isset($charge['price']) ? (float) $charge['price'] : null,
                    'charge_currency' => is_array($charge) ? ($charge['priceCurrency'] ?? null) : null,
                    'estimated_delivery_date' => is_string($estimated) ? substr($estimated, 0, 10) : null,
                    'pickup_confirmation_number' => is_string($pickup) && $pickup !== '' ? $pickup : null,
                    'pickup_requested_at' => is_string($pickup) && $pickup !== '' ? now() : null,
                    'events' => [],
                    'synced_at' => now(),
                ]);

                foreach ((array) ($answer['documents'] ?? []) as $document) {
                    if (! empty($document['content'])) {
                        $shipment->documents()->create([
                            'type' => (string) ($document['typeCode'] ?? 'label'),
                            'format' => Str::upper((string) ($document['imageFormat'] ?? 'PDF')),
                            'content' => (string) $document['content'],
                        ]);
                    }
                }

                Order::whereIn('id', $live->pluck('id'))->update([
                    'tracking_number' => $awb,
                    // What the desk corrected is what DHL now has: keep it on the orders.
                    'shipping_country' => Str::upper((string) $receiver['country']),
                    'shipping_postal_code' => filled($receiver['postal_code'] ?? null) ? Str::upper(trim((string) $receiver['postal_code'])) : null,
                ]);

                return $shipment;
            });
        });
    }

    /**
     * One of the shipment's documents. The proof of delivery is asked from
     * DHL the first time and kept.
     *
     * @throws DhlExpressException
     */
    public function document(Order $order, string $type): DhlShipmentDocument
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new DhlExpressException('Aucune expédition DHL pour cet achat.');

        $document = $shipment->documents()->where('type', $type)->latest('id')->first();

        if ($document) {
            return $document;
        }

        if ($type !== 'pod') {
            throw new DhlExpressException('DHL n’a pas fourni ce document pour cette expédition.');
        }

        $pod = collect($this->client->proofOfDelivery($shipment->tracking_number))
            ->first(fn ($d) => ! empty($d['content']));

        if (! $pod) {
            throw new DhlExpressException('DHL n’a pas encore de preuve de livraison pour cette expédition.');
        }

        return $shipment->documents()->create([
            'type' => 'pod',
            'format' => Str::upper((string) ($pod['encodingFormat'] ?? 'PDF')),
            'content' => (string) $pod['content'],
        ]);
    }

    /**
     * Book a DHL courier for a shipment created without one (or whose
     * booking was cancelled).
     *
     * @throws DhlExpressException
     */
    public function bookPickup(Order $order, string $date, string $closeTime, string $location): DhlShipment
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new DhlExpressException('Aucune expédition DHL pour cet achat.');

        if ($shipment->hasPickup()) {
            throw new DhlExpressException("Un ramassage DHL est déjà réservé ({$shipment->pickup_confirmation_number}).");
        }

        if ($shipment->closed_at || $shipment->status_code !== null) {
            throw new DhlExpressException('DHL a déjà pris en charge ce colis.');
        }

        $numbers = $this->client->requestPickup([
            'plannedPickupDateAndTime' => $this->plannedAt($date),
            'closeTime' => $closeTime,
            'location' => Str::limit($location, 80, ''),
            'locationType' => 'business',
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->client->accountNumber()]],
            'customerDetails' => ['shipperDetails' => $this->shipperDetails(withType: false)],
            'shipmentDetails' => [[
                'productCode' => $shipment->product_code,
                'isCustomsDeclarable' => true,
                'declaredValue' => (float) $shipment->declared_value,
                'declaredValueCurrency' => self::CURRENCY,
                'unitOfMeasurement' => 'metric',
                'shipmentTrackingNumber' => $shipment->tracking_number,
                'packages' => array_map(fn (array $p) => [
                    'weight' => (float) $p['weight'],
                    'dimensions' => [
                        'length' => (float) $p['length'],
                        'width' => (float) $p['width'],
                        'height' => (float) $p['height'],
                    ],
                ], $shipment->packages),
            ]],
        ]);

        if ($numbers === []) {
            throw new DhlExpressException('DHL n’a pas confirmé le ramassage.');
        }

        $shipment->update([
            'pickup_confirmation_number' => $numbers[0],
            'pickup_requested_at' => now(),
            'pickup_cancelled_at' => null,
        ]);

        return $shipment;
    }

    /**
     * Cancel the courier booking — the parcel will be dropped off instead,
     * or the purchase is not leaving. DHL's own answer is returned.
     *
     * @throws DhlExpressException
     */
    public function cancelPickup(Order $order): string
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new DhlExpressException('Aucune expédition DHL pour cet achat.');

        if (! $shipment->hasPickup()) {
            throw new DhlExpressException('Aucun ramassage DHL à annuler.');
        }

        $message = $this->client->cancelPickup(
            $shipment->pickup_confirmation_number,
            (string) (config('services.dhl_express.shipper.name') ?: 'Hirfaty'),
            'Annulé par l’expéditeur',
        );

        $shipment->update(['pickup_cancelled_at' => now()]);

        return $message;
    }

    /**
     * Read the purchase's checkpoints now, rather than waiting for the
     * scheduled refresh.
     *
     * @throws DhlExpressException
     */
    public function refreshFor(Order $order): bool
    {
        $shipment = $this->shipmentFor($order)
            ?? throw new DhlExpressException('Aucune expédition DHL pour cet achat.');

        $found = collect($this->client->track([$shipment->tracking_number]))
            ->firstWhere('shipmentTrackingNumber', $shipment->tracking_number);

        if (! $found) {
            $shipment->update(['synced_at' => now()]);

            return false;
        }

        return $this->apply($shipment, $found);
    }

    /**
     * Refresh every shipment still on its way, 200 per call — what the
     * scheduled command runs.
     *
     * @return array{checked: int, changed: int, failed: int}
     */
    public function refreshOpen(): array
    {
        $result = ['checked' => 0, 'changed' => 0, 'failed' => 0];

        if (! $this->client->isConfigured()) {
            return $result;
        }

        DhlShipment::whereNull('closed_at')
            ->where('created_at', '>=', now()->subDays(self::POLL_DAYS))
            ->orderBy('id')
            ->chunk(DhlExpressClient::TRACKING_BATCH, function ($chunk) use (&$result) {
                try {
                    $found = collect($this->client->track($chunk->pluck('tracking_number')->all()))
                        ->keyBy(fn ($s) => (string) ($s['shipmentTrackingNumber'] ?? ''));
                } catch (DhlExpressException $e) {
                    $result['failed'] += $chunk->count();
                    Log::warning('DHL tracking failed: '.$e->getMessage());

                    return;
                }

                foreach ($chunk as $shipment) {
                    $result['checked']++;

                    if ($data = $found->get($shipment->tracking_number)) {
                        $result['changed'] += $this->apply($shipment, $data) ? 1 : 0;
                    } else {
                        $shipment->update(['synced_at' => now()]);
                    }
                }
            });

        return $result;
    }

    /**
     * Keep what DHL says about one shipment and carry each checkpoint not
     * seen before onto the orders. Returns whether any was new.
     *
     * @param  array<string, mixed>  $tracked  One entry of DHL's `shipments`.
     */
    public function apply(DhlShipment $shipment, array $tracked): bool
    {
        $events = self::events((array) ($tracked['events'] ?? []));
        $seen = collect($shipment->events ?? [])->map(fn (array $e) => self::eventKey($e))->flip();
        $new = array_values(array_filter($events, fn (array $e) => ! $seen->has(self::eventKey($e))));
        $last = $events === [] ? null : $events[array_key_last($events)];
        $estimated = $tracked['estimatedDeliveryDate'] ?? null;

        $shipment->fill([
            'events' => $events === [] ? $shipment->events : $events,
            'status_code' => $last['code'] ?? $shipment->status_code,
            'status_description' => $last['description'] ?? $shipment->status_description,
            'estimated_delivery_date' => is_string($estimated) && $estimated !== '' ? substr($estimated, 0, 10) : $shipment->estimated_delivery_date,
            'synced_at' => now(),
        ]);

        $codes = array_column($events, 'code');

        if (in_array(self::DELIVERED, $codes, true) || ($last['code'] ?? null) === self::RETURNED) {
            $shipment->closed_at ??= now();
        }

        $shipment->save();

        foreach ($new as $event) {
            $this->carry($shipment, $event);
        }

        return $new !== [];
    }

    /**
     * DHL's checkpoints, oldest first, in our own shape.
     *
     * @param  array<int, mixed>  $raw
     * @return list<array{code: string, description: string, at: string|null, location: string|null}>
     */
    public static function events(array $raw): array
    {
        return collect($raw)
            ->filter(fn ($e) => is_array($e) && ! empty($e['typeCode']))
            ->map(fn (array $e) => [
                'code' => Str::upper((string) $e['typeCode']),
                'description' => Str::limit(trim((string) ($e['description'] ?? '')), 250, ''),
                'at' => self::eventTime($e),
                'location' => isset($e['serviceArea'][0]['description']) ? (string) $e['serviceArea'][0]['description'] : null,
            ])
            ->sortBy(fn (array $e) => $e['at'] ?? '')
            ->values()
            ->all();
    }

    /** One checkpoint carried onto the purchase. */
    private function carry(DhlShipment $shipment, array $event): void
    {
        $orders = $shipment->orders()->where('status', '!=', 'cancelled')->orderBy('id')->get();

        if ($orders->isEmpty()) {
            return;
        }

        $code = $event['code'];
        $attributes = ['tracking_number' => $shipment->tracking_number];

        if ($code === self::DELIVERED) {
            $undelivered = $orders->where('status', '!=', 'delivered');

            if ($undelivered->isNotEmpty()) {
                $this->statuses->transitionMany($undelivered->values(), 'delivered', $attributes);
            }

            return;
        }

        if (in_array($code, self::ISSUES, true)) {
            $this->notifications->notifyAdmins(new DhlDeliveryIssue(
                $shipment->purchase_reference,
                $orders->first()->reference,
                $shipment->tracking_number,
                trim("{$code} — {$event['description']}", ' —'),
            ));

            return;
        }

        // Any checkpoint means DHL has the parcel.
        $note = self::BUYER_NOTES[$code] ?? null;
        $toShip = $orders->whereIn('status', ['pending', 'confirmed']);

        if ($toShip->isNotEmpty()) {
            $this->statuses->transitionMany($toShip->values(), 'shipped', $attributes, $note);
        }

        $rest = $orders->whereNotIn('id', $toShip->pluck('id'))->where('status', '!=', 'delivered');

        if ($note !== null && $rest->isNotEmpty()) {
            $this->statuses->addNote($rest->values(), $note);
        }
    }

    /**
     * The request DHL is asked to create, from the desk's fields. Re-checks
     * what the form cannot guarantee.
     *
     * @param  Collection<int, Order>  $orders
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws DhlExpressException
     */
    private function payload(Collection $orders, array $fields): array
    {
        $reference = DhlShipment::referenceFor($orders->first());
        $receiver = (array) ($fields['receiver'] ?? []);
        $country = Str::upper(trim((string) ($receiver['country'] ?? '')));

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new DhlExpressException('Choisissez le pays du destinataire.');
        }

        if ($country === Str::upper((string) config('services.dhl_express.shipper.country', 'MA'))) {
            throw new DhlExpressException('DHL Express n’est utilisé que pour l’étranger : ce destinataire est au Maroc.');
        }

        $items = array_values((array) ($fields['items'] ?? []));

        if ($items === []) {
            throw new DhlExpressException('Aucun article à déclarer.');
        }

        $lineItems = [];
        $declared = 0.0;
        $netKg = 0.0;

        foreach ($items as $i => $item) {
            $quantity = max(1, (int) $item['quantity']);
            $price = round(max(0, (float) $item['price']), 2);
            $weight = round(max(0.001, (float) $item['weight']) * $quantity, 3);
            $hs = preg_replace('/\D/', '', (string) ($item['hs_code'] ?? ''));
            $description = self::ascii((string) $item['description'], 512);

            if ($description === '') {
                throw new DhlExpressException('Article '.($i + 1).' : la description douanière est vide.');
            }

            $declared += $price * $quantity;
            $netKg += $weight;

            $lineItems[] = array_filter([
                'number' => $i + 1,
                'description' => $description,
                'price' => $price,
                'quantity' => ['value' => $quantity, 'unitOfMeasurement' => 'PCS'],
                'commodityCodes' => $hs !== '' ? [['typeCode' => 'outbound', 'value' => substr($hs, 0, 18)]] : null,
                'exportReasonType' => 'permanent',
                'manufacturerCountry' => Str::upper((string) ($item['origin_country'] ?: 'MA')),
                'weight' => ['netValue' => $weight, 'grossValue' => $weight],
            ], fn ($v) => $v !== null);
        }

        $packages = array_map(fn (array $p) => [
            'weight' => round(max(0.1, (float) $p['weight']), 3),
            'dimensions' => [
                'length' => round(max(1, (float) $p['length']), 1),
                'width' => round(max(1, (float) $p['width']), 1),
                'height' => round(max(1, (float) $p['height']), 1),
            ],
        ], array_values((array) ($fields['packages'] ?? [])));

        if ($packages === []) {
            throw new DhlExpressException('Ajoutez au moins un colis.');
        }

        $incoterm = in_array($fields['incoterm'] ?? null, self::INCOTERMS, true) ? $fields['incoterm'] : 'DAP';
        $pickup = (array) ($fields['pickup'] ?? []);
        $plannedAt = $this->plannedAt((string) ($fields['shipping_date'] ?? ''));
        $grossKg = array_sum(array_column($packages, 'weight'));
        $email = trim((string) ($receiver['email'] ?? ''));
        $name = Str::limit(trim((string) ($receiver['name'] ?? '')), 255, '');

        return [
            'plannedShippingDateAndTime' => $plannedAt,
            'pickup' => ! empty($pickup['requested']) ? [
                'isRequested' => true,
                'closeTime' => (string) ($pickup['close_time'] ?? '18:00'),
                'location' => Str::limit((string) ($pickup['location'] ?? 'reception'), 80, ''),
            ] : ['isRequested' => false],
            'productCode' => (string) config('services.dhl_express.product_code', 'P'),
            'getRateEstimates' => true,
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->client->accountNumber()]],
            'customerReferences' => [['typeCode' => 'CU', 'value' => Str::limit($reference, 35, '')]],
            'outputImageProperties' => [
                'encodingFormat' => 'pdf',
                'imageOptions' => [
                    ['typeCode' => 'label', 'templateName' => self::LABEL_TEMPLATE],
                    ['typeCode' => 'invoice', 'templateName' => self::INVOICE_TEMPLATE, 'isRequested' => true, 'invoiceType' => 'commercial'],
                    ['typeCode' => 'waybillDoc', 'isRequested' => true],
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => $this->shipperDetails(),
                'receiverDetails' => [
                    'postalAddress' => array_filter([
                        'postalCode' => Str::limit(Str::upper(trim((string) ($receiver['postal_code'] ?? ''))), 12, ''),
                        'cityName' => Str::limit(trim((string) ($receiver['city'] ?? '')), 45, ''),
                        'countryCode' => $country,
                        'addressLine1' => Str::limit(trim((string) ($receiver['address_line1'] ?? '')), 45, ''),
                        'addressLine2' => Str::limit(trim((string) ($receiver['address_line2'] ?? '')), 45, ''),
                        'addressLine3' => Str::limit(trim((string) ($receiver['address_line3'] ?? '')), 45, ''),
                    ], fn ($v, $k) => $k === 'postalCode' || $v !== '', ARRAY_FILTER_USE_BOTH),
                    'contactInformation' => array_filter([
                        'phone' => Str::limit(trim((string) ($receiver['phone'] ?? '')), 70, ''),
                        'companyName' => Str::limit($name, 100, ''),
                        'fullName' => $name,
                        'email' => $email !== '' ? Str::limit($email, 70, '') : null,
                    ]),
                    'typeCode' => 'private',
                ],
            ],
            'content' => [
                'packages' => $packages,
                'isCustomsDeclarable' => true,
                'declaredValue' => round($declared, 2),
                'declaredValueCurrency' => self::CURRENCY,
                'exportDeclarations' => [[
                    'lineItems' => $lineItems,
                    'invoice' => [
                        'number' => Str::limit($reference, 35, ''),
                        'date' => substr($plannedAt, 0, 10),
                        'totalNetWeight' => round($netKg, 3),
                        'totalGrossWeight' => round(max($grossKg, $netKg), 3),
                    ],
                    // Required on each declaration as well as on the content (3.4.1).
                    'incoterm' => $incoterm,
                    'exportReasonType' => 'permanent',
                    'shipmentType' => 'commercial',
                ]],
                'description' => self::ascii($this->contents($lineItems), 70),
                'incoterm' => $incoterm,
                'unitOfMeasurement' => 'metric',
            ],
            ...(! empty($fields['notify_buyer']) && $email !== '' ? ['shipmentNotification' => [[
                'typeCode' => 'email',
                'receiverId' => Str::limit($email, 70, ''),
                'languageCode' => 'eng',
            ]]] : []),
            'estimatedDeliveryDate' => ['isRequested' => true, 'typeCode' => 'QDDC'],
        ];
    }

    /**
     * The desk, as DHL prints it on the label and comes to collect.
     *
     * @return array<string, mixed>
     */
    private function shipperDetails(bool $withType = true): array
    {
        $shipper = (array) config('services.dhl_express.shipper');

        return array_filter([
            'postalAddress' => [
                'postalCode' => (string) ($shipper['postal_code'] ?? ''),
                'cityName' => (string) ($shipper['city'] ?? ''),
                'countryCode' => Str::upper((string) ($shipper['country'] ?? 'MA')),
                'addressLine1' => Str::limit((string) ($shipper['address'] ?? ''), 45, ''),
            ],
            'contactInformation' => array_filter([
                'phone' => (string) ($shipper['phone'] ?? ''),
                'companyName' => (string) ($shipper['company'] ?? 'Hirfaty'),
                'fullName' => (string) ($shipper['name'] ?? ''),
                'email' => (string) ($shipper['email'] ?? ''),
            ]),
            'typeCode' => $withType ? 'business' : null,
        ]);
    }

    /**
     * "2026-11-18T10:00:00 GMT+01:00", Casablanca time. Today means an hour
     * from now (DHL refuses a time already past when the request lands);
     * another day means 10:00.
     *
     * @throws DhlExpressException
     */
    private function plannedAt(string $date): string
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
        } catch (\Throwable) {
            $day = false;
        }

        if (! $day || $day->lt($today) || $day->gt($today->addDays(self::MAX_DAYS_AHEAD))) {
            throw new DhlExpressException('La date d’expédition doit être entre aujourd’hui et dans '.self::MAX_DAYS_AHEAD.' jours.');
        }

        $at = $day->isSameDay($today)
            ? CarbonImmutable::now(self::TIMEZONE)->addHour()->ceilHour()
            : $day->setTime(10, 0);

        if (! $at->isSameDay($day)) {
            throw new DhlExpressException('Trop tard pour expédier aujourd’hui : choisissez demain.');
        }

        return $at->format('Y-m-d\TH:i:s').' GMT'.$at->format('P');
    }

    /** Tomorrow, or Monday after a Friday or a weekend. */
    private function defaultShippingDate(): string
    {
        $day = CarbonImmutable::now(self::TIMEZONE)->addDay();

        while ($day->isWeekend()) {
            $day = $day->addDay();
        }

        return $day->toDateString();
    }

    /**
     * Why the purchase cannot be handed to DHL at all, in the desk's words —
     * null when it can.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function blocker(Collection $orders): ?string
    {
        $first = $this->live($orders)->first();

        if (! $this->client->isConfigured()) {
            return 'Identifiants DHL Express non configurés (DHL_EXPRESS_USERNAME / DHL_EXPRESS_PASSWORD / DHL_EXPRESS_ACCOUNT_NUMBER).';
        }

        $shipper = (array) config('services.dhl_express.shipper');
        $missing = collect(['name' => 'NAME', 'phone' => 'PHONE', 'address' => 'ADDRESS', 'city' => 'CITY', 'postal_code' => 'POSTAL_CODE'])
            ->filter(fn (string $env, string $key) => blank($shipper[$key] ?? null))
            ->map(fn (string $env) => "DHL_EXPRESS_SHIPPER_{$env}");

        if ($missing->isNotEmpty()) {
            return 'Adresse d’expédition DHL incomplète : renseignez '.$missing->implode(', ').'.';
        }

        if (! $first) {
            return 'Toutes les commandes de cet achat sont annulées.';
        }

        if (! $this->shipsWithDhl($first)) {
            return 'Cet achat n’est pas livré par DHL Express.';
        }

        if ($this->live($orders)->contains(fn (Order $o) => $o->status === 'delivered')) {
            return 'Cet achat est déjà livré.';
        }

        if ($first->paysOnline() && $first->payment_status !== 'paid') {
            return 'Le paiement en ligne de cet achat n’est pas réglé.';
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
        $live = $this->live($orders);
        $first = $live->first();
        $warnings = [];

        if (blank($first->shipping_country)) {
            $warnings[] = 'Le pays de destination n’a pas été enregistré avec cette commande : choisissez-le.';
        }

        if (blank($first->shipping_city) && blank($first->city?->name)) {
            $warnings[] = 'La ville du destinataire est dans l’adresse saisie : vérifiez le champ « Ville » (DHL propose la ville du code postal).';
        }

        if (! str_starts_with(trim((string) $first->shipping_phone), '+') && ! str_starts_with(trim((string) $first->shipping_phone), '00')) {
            $warnings[] = 'Le téléphone n’a pas d’indicatif international (+33…, +1…) : ajoutez-le, le livreur DHL en a besoin.';
        }

        $items = $live->flatMap->items;
        $noHs = $items->filter(fn (OrderItem $i) => $i->product && blank($this->shipping->hsCode($i->product)));

        if ($noHs->isNotEmpty()) {
            $warnings[] = 'Code SH manquant pour : '.$noHs->map(fn (OrderItem $i) => $i->product->name)->unique()->implode(', ').'. La douane du pays d’arrivée peut retenir le colis.';
        }

        $noWeight = $items->filter(fn (OrderItem $i) => $i->product && $this->shipping->weightKg($i->product, $i->variant) === null);

        if ($noWeight->isNotEmpty()) {
            $warnings[] = 'Poids inconnu pour : '.$noWeight->map(fn (OrderItem $i) => $i->product->name)->unique()->implode(', ').' (compté '.$this->defaultPieceKg().' kg). Pesez le colis.';
        }

        if ($this->client->isTest()) {
            $warnings[] = 'Environnement de TEST DHL : l’expédition sera fictive (aucun coursier, aucune facture), et son suivi restera vide.';
        }

        return $warnings;
    }

    /**
     * What the panel shows of a shipment.
     *
     * @return array<string, mixed>
     */
    private function present(DhlShipment $shipment): array
    {
        $types = $shipment->documents()->pluck('type')->unique();

        return [
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => 'https://www.dhl.com/ma-fr/home/suivi.html?tracking-id='.$shipment->tracking_number,
            'pieces' => $shipment->piece_tracking_numbers ?? [],
            'incoterm' => $shipment->incoterm,
            'planned_shipping_date' => $shipment->planned_shipping_date?->toDateString(),
            'estimated_delivery_date' => $shipment->estimated_delivery_date?->toDateString(),
            'charge' => $shipment->charge === null ? null : (float) $shipment->charge,
            'charge_currency' => $shipment->charge_currency,
            'pickup' => [
                'number' => $shipment->pickup_confirmation_number,
                'requested_at' => $shipment->pickup_requested_at?->toIso8601String(),
                'cancelled_at' => $shipment->pickup_cancelled_at?->toIso8601String(),
                'active' => $shipment->hasPickup(),
                // Bookable while DHL has not scanned the parcel yet.
                'bookable' => ! $shipment->hasPickup() && $shipment->status_code === null && $shipment->closed_at === null,
            ],
            'status_code' => $shipment->status_code,
            'status_description' => $shipment->status_description,
            'events' => array_reverse(array_slice($shipment->events ?? [], -8)),
            'documents' => collect(self::DOCUMENT_TYPES)
                ->filter(fn (string $t) => $types->contains($t) || ($t === 'pod' && in_array(self::DELIVERED, array_column($shipment->events ?? [], 'code'), true)))
                ->values()->all(),
            'issue' => in_array($shipment->status_code, self::ISSUES, true),
            'delivered' => $shipment->status_code === self::DELIVERED,
            'synced_at' => $shipment->synced_at?->toIso8601String(),
            'closed' => $shipment->closed_at !== null,
        ];
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
     * The address without the "town postcode" it ends on (DHL has fields for
     * both), cut at commas then words into DHL's 45-character lines — at
     * most three.
     *
     * @return list<string>
     */
    private function addressLines(string $address, ?string $postalCode, ?string $town = null): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn ($p) => $p !== ''));
        $tail = collect([
            trim(($town ?? '').' '.($postalCode ?? '')),
            (string) $postalCode,
            (string) $town,
        ])->map(fn (string $t) => Str::upper(trim($t)))->filter()->unique();

        if ($parts !== [] && $tail->contains(Str::upper($parts[array_key_last($parts)]))) {
            array_pop($parts);
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', implode(', ', $parts)) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) > 45 && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        // Three lines at most: the rest joins the last one, cut to fit.
        if (count($lines) > 3) {
            $lines = [...array_slice($lines, 0, 2), implode(' ', array_slice($lines, 2))];
        }

        return array_map(fn (string $l) => mb_substr(trim($l, ' ,'), 0, 45), $lines);
    }

    /** "Ceramic plate — Plat en céramique", as customs reads it. */
    private function customsDescription(OrderItem $item): string
    {
        $product = $item->product;
        $text = trim((string) ($product?->customs_description ?: $product?->name ?: 'Handmade craft item'));

        if ($item->variant_label) {
            $text .= ' ('.$item->variant_label.')';
        }

        return self::ascii($text, 512);
    }

    private function unitWeightKg(OrderItem $item): float
    {
        $kg = $item->product ? $this->shipping->weightKg($item->product, $item->variant) : null;

        return round($kg ?? $this->defaultPieceKg(), 3);
    }

    private function defaultPieceKg(): float
    {
        $weight = DeliveryService::where('slug', DhlDeliveryRateSeeder::SLUG)->value('default_piece_weight');

        return $weight !== null ? (float) $weight : 1.0;
    }

    /**
     * The box the draft proposes: the largest piece's measures, else a
     * standard box. The desk measures the real one.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return array{length: float, width: float, height: float}
     */
    private function boxFor(Collection $items): array
    {
        $largest = collect($this->shipping->pieces($items))
            ->pluck('dimensions')
            ->filter()
            ->sortByDesc(fn (array $d) => $d['length'] * $d['width'] * $d['height'])
            ->first();

        return $largest
            ? ['length' => (float) $largest['length'], 'width' => (float) $largest['width'], 'height' => (float) $largest['height']]
            : self::DEFAULT_BOX_CM;
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     */
    private function contents(array $lineItems): string
    {
        $text = collect($lineItems)->map(fn (array $l) => $l['description'])->implode(', ');

        return $text !== '' ? $text : 'Handmade crafts';
    }

    /**
     * DHL takes only printable ASCII in descriptions: accents and Arabic are
     * transliterated, anything left is dropped.
     */
    public static function ascii(string $text, int $max): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($text)) ?? '';

        return Str::limit(trim(preg_replace('/\s+/', ' ', $ascii) ?? ''), $max, '');
    }

    private static function eventTime(array $event): ?string
    {
        if (empty($event['date'])) {
            return null;
        }

        $offset = (string) ($event['GMTOffset'] ?? '');

        return trim($event['date'].'T'.($event['time'] ?? '00:00:00').(preg_match('/^[+-]\d{2}:\d{2}$/', $offset) ? $offset : ''));
    }

    private static function eventKey(array $event): string
    {
        return ($event['code'] ?? '').'|'.($event['at'] ?? '').'|'.($event['description'] ?? '');
    }

    /** @return Collection<int, Order> */
    private function purchase(Order $order): Collection
    {
        return $order->groupOrders()
            ->with(['buyer', 'deliveryService', 'city', 'items.product.category', 'items.variant'])
            ->orderBy('id')
            ->get();
    }

    private function shipsWithDhl(Order $order): bool
    {
        return $order->deliveryService?->slug === DhlDeliveryRateSeeder::SLUG;
    }

    private function shipmentFor(Order $order): ?DhlShipment
    {
        return DhlShipment::where('purchase_reference', DhlShipment::referenceFor($order))->first();
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
