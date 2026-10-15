<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The admin invoices table.
 *
 * One row per invoice, and an invoice is one *purchase* — the same grain as the
 * PDF the buyer downloads (PurchaseInvoiceService), so the number on a row is
 * the number printed on the invoice: the purchase's group reference, or the
 * order's own reference for one placed before grouping existed.
 *
 * Hirfaty issues these invoices itself, whatever the buyer paid with. PayPal's
 * Invoicing API is for billing someone through a PayPal-hosted invoice; a
 * checkout paid with PayPal never creates one there, so there is nothing to
 * pull from it. What PayPal (or Payzone) actually took is shown beside each
 * invoice instead, from the purchase's Payment row.
 *
 * Figures are what the buyer paid — products plus delivery, in MAD — never the
 * commission or the artisan's share.
 */
class AdminInvoiceListService
{
    public const METHODS = [Order::PAYMENT_CARD, Order::PAYMENT_PAYPAL, Order::PAYMENT_COD];

    private const PER_PAGE = 25;

    /**
     * The invoice number of an order row, as SQL. Selected and grouped by as
     * the same literal text: Postgres only accepts a grouped expression in
     * the select list when the two match exactly.
     */
    private const NUMBER = 'COALESCE(orders.group_reference, orders.reference)';

    /**
     * One page of invoices, newest first, under the search and payment-method
     * tab.
     *
     * Paginated over purchases, not order rows (unlike the orders table), so a
     * page holds exactly PER_PAGE invoices and none is split across two pages.
     *
     * @param  array{search?: ?string, method?: ?string}  $filters
     * @return array{invoices: Collection<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function page(array $filters): array
    {
        $paginator = $this->query($filters)
            ->selectRaw(self::NUMBER.' as invoice_number')
            ->selectRaw('MIN(orders.id) as first_id')
            ->groupByRaw(self::NUMBER)
            ->orderByDesc('first_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $numbers = collect($paginator->items())->pluck('invoice_number');

        return [
            'invoices' => $this->rows($numbers),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'links' => $paginator->linkCollection(),
            ],
        ];
    }

    /**
     * How many invoices each payment-method tab holds, under the current
     * search — `all` included.
     *
     * @param  array{search?: ?string, method?: ?string}  $filters
     * @return array<string, int>
     */
    public function counts(array $filters): array
    {
        $byMethod = $this->query(['search' => $filters['search'] ?? null])
            ->toBase()
            ->selectRaw('orders.payment_method as method')
            ->selectRaw('COUNT(DISTINCT '.self::NUMBER.') as total')
            ->groupBy('orders.payment_method')
            ->pluck('total', 'method')
            ->map(fn ($total) => (int) $total);

        $counts = ['all' => $byMethod->sum()];

        foreach (self::METHODS as $method) {
            $counts[$method] = $byMethod->get($method, 0);
        }

        return $counts;
    }

    /**
     * The filters and nothing else. A purchase's orders share one buyer and
     * one payment method, so matching any of its rows matches the invoice.
     *
     * @param  array{search?: ?string, method?: ?string}  $filters
     * @return Builder<Order>
     */
    private function query(array $filters): Builder
    {
        $method = in_array($filters['method'] ?? null, self::METHODS, true) ? $filters['method'] : null;

        return Order::query()
            ->when(trim((string) ($filters['search'] ?? '')), fn ($query, $search) => $query->where(function ($q) use ($search) {
                // References are stored uppercase — see AdminOrderListService.
                $q->where('orders.reference', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('orders.group_reference', 'like', '%'.strtoupper($search).'%')
                    ->orWhereHas('buyer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            }))
            ->when($method, fn ($query, $method) => $query->where('orders.payment_method', $method));
    }

    /**
     * The invoices behind these numbers, in the same order, each rebuilt from
     * *all* of its orders — the search may have matched only some of them.
     *
     * @param  Collection<int, string>  $numbers
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $numbers): Collection
    {
        if ($numbers->isEmpty()) {
            return collect();
        }

        $orders = Order::query()
            ->where(fn ($query) => $query
                ->whereIn('group_reference', $numbers)
                ->orWhere(fn ($query) => $query
                    ->whereNull('group_reference')
                    ->whereIn('reference', $numbers)))
            ->with(['buyer:id,first_name,last_name,email', 'store:id,store_name'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Order $order) => $order->group_reference ?? $order->reference);

        // The attempt that took the money, for a purchase paid online. keyBy
        // keeps the last row, which is the latest attempt.
        $payments = Payment::query()
            ->whereIn('group_reference', $numbers)
            ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->orderBy('id')
            ->get()
            ->keyBy('group_reference');

        return $numbers
            ->filter(fn (string $number) => $orders->has($number))
            ->map(fn (string $number) => $this->row($number, $orders->get($number), $payments->get($number)))
            ->values();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    private function row(string $number, Collection $orders, ?Payment $payment): array
    {
        $first = $orders->first();
        $buyer = $first->buyer;
        $buyerName = trim(($buyer?->first_name ?? '').' '.($buyer?->last_name ?? ''));

        // Delivery sits on the first order of a split — see CheckoutService.
        $productsTotal = (float) $orders->sum(fn (Order $order) => (float) $order->total_amount);
        $deliveryTotal = (float) $orders->sum(fn (Order $order) => (float) $order->delivery_fee);

        return [
            'number' => $number,
            // What a link to the purchase points at: admin.orders.show binds
            // a single order and rebuilds the group from it.
            'primary_reference' => $first->reference,
            'issued_at' => $first->created_at,
            'buyer' => [
                // The invoice outlives the account it was issued to.
                'name' => match (true) {
                    $buyer === null => 'Compte supprimé',
                    $buyerName !== '' => $buyerName,
                    default => $buyer->email ?? '—',
                },
                'email' => $buyer?->email,
            ],
            'stores' => $orders->map(fn (Order $order) => $order->store?->store_name)->filter()->unique()->values(),
            'products_total' => $productsTotal,
            'delivery_total' => $deliveryTotal,
            'total' => round($productsTotal + $deliveryTotal, 2),
            'payment_method' => $first->payment_method,
            'state' => $this->state($orders),
            // What the provider took, in its own currency: MAD for Payzone,
            // EUR/USD… for PayPal, with the id to look it up there.
            'payment' => $payment ? [
                'provider' => $payment->provider,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'transaction_id' => $payment->provider_transaction_id,
            ] : null,
        ];
    }

    /**
     * Where the money stands on one invoice:
     * `refunded` / `partially_refunded` — given back, in whole or in part;
     * `cancelled` — called off with nothing refunded;
     * `paid` — taken online; `collected` / `to_collect` — cash, once every
     * parcel still standing is delivered, or before.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function state(Collection $orders): string
    {
        $refunded = $orders->where('payment_status', 'refunded')->count();

        if ($refunded > 0) {
            return $refunded === $orders->count() ? 'refunded' : 'partially_refunded';
        }

        $standing = $orders->reject(fn (Order $order) => $order->isCancelled());

        return match (true) {
            $standing->isEmpty() => 'cancelled',
            $orders->first()->paysOnline() => 'paid',
            $standing->every(fn (Order $order) => $order->isDelivered()) => 'collected',
            default => 'to_collect',
        };
    }
}
