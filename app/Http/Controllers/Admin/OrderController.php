<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdminOrderListService;
use App\Services\OrderStatusService;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The fulfilment desk's view of orders.
 *
 * Presented by *purchase*, not by order row. `CheckoutService` splits a basket
 * into one order per workshop because each artisan is paid separately, but
 * Hirfaty collects and delivers the whole basket as one job — so a buyer who
 * bought from three workshops is one line of work here, not three.
 *
 * Grouping is on `orders.group_reference` (see its migration). Orders placed
 * before that column existed have none; they fall back to their own reference,
 * so they simply appear as groups of one rather than vanishing.
 */
class OrderController extends Controller
{
    public function index(Request $request, AdminOrderListService $list)
    {
        $filters = $request->only(['search', 'status', 'from', 'to']);

        $query = $list->query($filters)->with(['buyer', 'store', 'items.product.images', 'city']);

        /*
         * Paginated over orders, then folded into groups — not paginated over
         * groups directly. A GROUP BY paginator would have to count distinct
         * references in a subquery on every page, and the split is small (a
         * basket spans a handful of workshops at most), so the simpler query
         * wins. The trade-off is that a page holds 40 order rows rather than
         * exactly 20 purchases; `perPage` is set high enough that a group is
         * very unlikely to straddle a page boundary.
         */
        $paginator = $query->latest()->paginate(40)->withQueryString();

        $groups = collect($paginator->items())
            ->groupBy(fn (Order $order) => $order->group_reference ?? $order->reference)
            ->map(fn ($orders, $reference) => $this->summariseGroup($reference, $orders))
            ->values();

        return Inertia::render('admin/orders/index', [
            'groups' => $groups,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'links' => $paginator->linkCollection(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Download the orders the desk is looking at — every page, under the
     * current search, status and dates — as CSV or Excel.
     */
    public function export(Request $request, AdminOrderListService $list)
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(SpreadsheetExportService::FORMATS)],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,confirmed,shipped,delivered,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return $list->export($data['format'], $data);
    }

    /**
     * One purchase, whole — every workshop's order in the same checkout.
     *
     * Route-model-bound on a single order because that is what a link carries;
     * the group is reassembled from it here.
     */
    public function show(Order $order)
    {
        $orders = $order->groupOrders()
            ->with(['buyer', 'store', 'items.product.images', 'city'])
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/orders/show', [
            'group' => $this->summariseGroup(
                $order->group_reference ?? $order->reference,
                $orders,
            ),
        ]);
    }

    /**
     * Move a purchase — or one workshop's parcel out of it — to a new status.
     *
     * `scope` is what the "handle this workshop separately" checkbox sends.
     * The whole group is the default because the desk normally collects
     * everything and ships it as one parcel under one tracking number; a
     * single order is the exception, for when one workshop's piece has to
     * travel on its own.
     */
    public function updateStatus(Request $request, Order $order, OrderStatusService $orderStatus)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,shipped,delivered,cancelled'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'in:group,single'],
            // Shown to the buyer on their tracking page, against this step.
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $targets = $this->targets($order, $data['scope'] ?? null);

        $attributes = array_filter(
            ['tracking_number' => $data['tracking_number'] ?? null],
            fn ($value) => $value !== null,
        );

        // One call for the whole target set, not one per order: the earning
        // flip and the DB write are still per order inside the service, but
        // the buyer email is for the group — see transitionMany()'s
        // docblock for why looping transition() here used to send the buyer
        // one email per workshop for what was one status change.
        $orderStatus->transitionMany($targets, $data['status'], $attributes, $data['note'] ?? null);

        return back()->with('success', 'Statut mis à jour.');
    }

    /**
     * A tracking update without a status change — where the parcel is now.
     * Same `scope` rule as updateStatus(): the whole purchase by default.
     */
    public function addNote(Request $request, Order $order, OrderStatusService $orderStatus)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:500'],
            'scope' => ['nullable', 'in:group,single'],
        ]);

        $orderStatus->addNote($this->targets($order, $data['scope'] ?? null), $data['note']);

        return back()->with('success', 'Mise à jour du suivi publiée.');
    }

    /**
     * The orders an action applies to: the whole purchase, or — with
     * `scope: single` — the one workshop's parcel the desk ticked.
     *
     * @return Collection<int, Order>
     */
    private function targets(Order $order, ?string $scope)
    {
        return $scope === 'single'
            ? collect([$order])
            : $order->groupOrders()->orderBy('id')->get();
    }

    /**
     * Flatten one checkout's orders into the shape both admin screens read.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    private function summariseGroup(string $reference, $orders): array
    {
        $first = $orders->first();
        $buyer = $first->buyer;

        $buyerName = trim(($buyer->first_name ?? '').' '.($buyer->last_name ?? ''));

        return [
            'reference' => $reference,
            // What a link to this purchase points at — route-model binding is
            // on a single order, and the group is rebuilt from it in show().
            'primary_reference' => $first->reference,
            'created_at' => $first->created_at,
            'buyer' => [
                'name' => $buyerName !== '' ? $buyerName : ($buyer->email ?? '—'),
                'email' => $buyer->email ?? null,
            ],
            'shipping_address' => $first->shipping_address,
            'shipping_phone' => $first->shipping_phone,
            'city_name' => $first->city?->name,
            'notes' => $first->notes,

            /*
             * Money is summed across the whole purchase. `delivery_fee` lands
             * entirely on whichever order was created first (see
             * CheckoutService), so summing is what recovers the one delivery
             * charge the buyer actually paid.
             */
            'products_total' => (float) $orders->sum(fn (Order $o) => (float) $o->total_amount),
            'delivery_total' => (float) $orders->sum(fn (Order $o) => (float) $o->delivery_fee),

            /*
             * What the buyer was reading when they ordered, and the rate that
             * was live then. Every figure above is MAD and the desk collects
             * MAD — this exists purely so "they say they saw €38" is
             * answerable months later, once the rate has moved.
             *
             * Taken from the first order: one checkout produces the whole
             * group, so they all carry the same pair.
             */
            'display_currency' => $first->display_currency,
            'display_rate' => $first->display_rate === null ? null : (float) $first->display_rate,

            /*
             * What the parcel shipped by, snapshotted at checkout so a carrier
             * renamed later still reads correctly on dispatch screens.
             */
            'delivery_service_name' => $first->delivery_service_name ?? 'Amana',

            /*
             * One status when every parcel agrees, null when they have been
             * moved apart — which is exactly the state the "handle separately"
             * checkbox creates, so the page can say so rather than pick one
             * order's status and present it as the purchase's.
             */
            'status' => $orders->pluck('status')->unique()->count() === 1
                ? $orders->first()->status
                : null,

            'tracking_numbers' => $orders->pluck('tracking_number')->filter()->unique()->values(),

            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'status' => $o->status,
                'tracking_number' => $o->tracking_number,
                'total_amount' => (float) $o->total_amount,
                'delivery_fee' => (float) $o->delivery_fee,
                'store' => [
                    'id' => $o->store?->id,
                    'name' => $o->store?->store_name ?? '—',
                ],
                'items' => $o->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product?->name ?? 'Produit supprimé',
                    'variant_label' => $item->variant_label,
                    'quantity' => $item->quantity,
                    'subtotal' => (float) $item->subtotal,
                    'image' => $item->product?->images->first()?->url,
                ])->values(),
            ])->values(),
        ];
    }
}
