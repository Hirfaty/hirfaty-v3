<?php

namespace App\Services;

use App\Http\Resources\BuyerProductResource;
use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Everything the buyer's account area renders.
 *
 * The area is four sibling pages sharing one sidebar, each with its own thin
 * controller. Shaping their props here rather than in those controllers keeps
 * the assembly in one place and lets any of them reuse a part — the identity
 * block, say — without duplicating the mapping.
 *
 * The pages themselves are placeholders for now, but these methods already
 * return the finished shapes, so filling them in is frontend-only work.
 */
class BuyerAccountService
{
    /**
     * Identity, as the header and profile form need it.
     *
     * @return array<string, mixed>
     */
    public function identity(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * Account type, and everything the card needs to render its four states:
     * ordinary buyer, request pending, request declined, or a verified trade
     * account.
     *
     * `isProfessional` and `isVerified` are separate because they can
     * legitimately disagree — an admin can set the role by hand without
     * stamping the verification, and only the verified pair actually earns
     * wholesale prices (Product::audienceFor).
     *
     * @return array<string, mixed>
     */
    public function accountType(User $user): array
    {
        return [
            'isProfessional' => $user->isProfessionalBuyer(),
            'isVerified' => $user->isVerifiedProfessionalBuyer(),
            'requestStatus' => $user->professional_request_status,
            'requestMessage' => $user->professional_request_message,
            'requestedAt' => $user->professional_requested_at,
            'declineReason' => $user->professional_decline_reason,
            'canRequest' => $user->canRequestProfessional(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addresses(User $user): array
    {
        return $user->addresses()->with('city')->get()
            ->map(fn (Address $address) => $this->shapeAddress($address))
            ->all();
    }

    /**
     * The one address checkout pre-fills, which is the only one /account
     * shows: the page edits it in place rather than offering an address book,
     * so it never leaves a buyer with saved addresses they can't see.
     *
     * `city_id` rather than the city's name, because this feeds an edit form
     * and has to round-trip back into the select.
     *
     * @return array<string, mixed>|null
     */
    public function defaultAddress(User $user): ?array
    {
        $address = $user->defaultAddress()->with('city')->first();

        return $address ? $this->shapeAddress($address) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'recipient_phone' => $address->recipient_phone,
            'address_line' => $address->address_line,
            'city_id' => $address->city_id,
            'city' => $address->city?->name,
            'is_default' => $address->is_default,
        ];
    }

    /**
     * How far along a status is, for rolling several orders into one.
     *
     * `cancelled` is deliberately absent: it is not a point on this line but a
     * dead end, and mixing it in would make one cancelled shop drag a whole
     * purchase backwards. It is handled separately in statusFor().
     */
    private const STATUS_RANK = [
        'pending' => 0,
        'confirmed' => 1,
        'shipped' => 2,
        'delivered' => 3,
    ];

    /**
     * The buyer's order history, grouped back into purchases.
     *
     * Checkout splits one basket into an Order per store, because that is who
     * gets paid and who gets notified — but the buyer made a single purchase
     * and should see a single entry for it. `group_reference` is what every
     * order from one checkout shares (see CheckoutService), so it is what the
     * grouping is keyed on; orders placed before it existed have none and fall
     * back to standing alone, which is how they already read.
     *
     * Each purchase keeps its per-store orders intact underneath: the status,
     * the tracking number and the rating form are all genuinely per shop, and
     * flattening them away would leave the page unable to say which of three
     * workshops has actually shipped.
     *
     * Unpaginated on purpose: grouping only makes the list shorter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function purchases(User $user): array
    {
        $orders = Order::where('buyer_id', $user->id)
            ->with(self::PURCHASE_RELATIONS)
            ->latest()
            ->get();

        $productReviews = $this->productReviews($user);

        return $orders
            /*
             * Legacy orders carry no group reference, and grouping them all
             * under `null` would collapse a buyer's entire pre-grouping
             * history into one card. Keying those on their own id instead
             * leaves each standing alone.
             */
            ->groupBy(fn (Order $order) => $order->group_reference ?: 'single:'.$order->id)
            ->map(fn (Collection $group) => $this->shapePurchase($group, $productReviews))
            ->values()
            ->all();
    }

    /**
     * One purchase, as the order history would render its card — for the
     * confirmation page checkout lands on.
     *
     * Null when the reference is unknown or belongs to someone else; the
     * caller turns that into a 404 so a guessed reference reveals nothing.
     *
     * @return array<string, mixed>|null
     */
    public function purchase(User $user, string $reference): ?array
    {
        $group = $this->purchaseOrders($user, $reference);

        return $group->isEmpty()
            ? null
            : $this->shapePurchase($group, $this->productReviews($user));
    }

    /**
     * Every order behind one purchase reference, scoped to its buyer.
     *
     * Matches either a group reference or — for an order placed before
     * grouping existed — that lone order's own reference, which is what
     * purchases() quotes for it. The buyer scope sits on the query rather
     * than being checked afterwards, so another buyer's reference simply
     * finds nothing.
     *
     * @return Collection<int, Order>
     */
    public function purchaseOrders(User $user, string $reference): Collection
    {
        return Order::where('buyer_id', $user->id)
            ->where(fn ($query) => $query
                ->where('group_reference', $reference)
                ->orWhere(fn ($query) => $query
                    ->whereNull('group_reference')
                    ->where('reference', $reference)))
            ->with(self::PURCHASE_RELATIONS)
            ->oldest('id')
            ->get();
    }

    /**
     * One purchase's history, for the tracking page: the purchase card's
     * data, plus every event across its workshops as one timeline.
     *
     * The desk usually moves a whole purchase at once, which writes the same
     * event on every order in it — so events are folded back together when
     * they say the same thing at the same minute. One that only touched some
     * workshops (a parcel shipped separately) keeps their names, so the buyer
     * can tell which part of the purchase it was about.
     *
     * @return array<string, mixed>|null
     */
    public function tracking(User $user, string $reference): ?array
    {
        $group = $this->purchaseOrders($user, $reference);

        if ($group->isEmpty()) {
            return null;
        }

        $group->load('statusEvents');

        $events = $group
            ->flatMap(fn (Order $order) => $order->statusEvents->map(fn ($event) => [
                'event' => $event,
                'store' => $order->store?->store_name,
            ]))
            ->groupBy(fn (array $row) => implode('|', [
                $row['event']->status ?? 'note',
                $row['event']->note ?? '',
                $row['event']->created_at?->format('Y-m-d H:i'),
            ]))
            ->map(function (Collection $rows) use ($group) {
                $event = $rows->first()['event'];
                $stores = $rows->pluck('store')->filter()->unique()->values();

                return [
                    'id' => $event->id,
                    'status' => $event->status,
                    'note' => $event->note,
                    'at' => $event->created_at,
                    // Null when it applied to the whole purchase.
                    'stores' => $group->count() > 1 && $rows->count() < $group->count()
                        ? $stores->all()
                        : null,
                ];
            })
            ->sortBy([['at', 'asc'], ['id', 'asc']])
            ->values();

        return [
            'purchase' => $this->shapePurchase($group, $this->productReviews($user)),
            // Newest first: what the buyer came to find out is the latest.
            'events' => $events->reverse()->values()->all(),
            /*
             * When the purchase first reached each step, for the progress bar.
             * The earliest across workshops: "shipped" lights up as soon as
             * the first parcel leaves, while the purchase's own status (the
             * least advanced workshop) says whether everything has.
             */
            'reached' => $events
                ->whereNotNull('status')
                ->groupBy('status')
                ->map(fn (Collection $rows) => $rows->first()['at'])
                ->all(),
        ];
    }

    /** What shapePurchase() reads, loaded up front so a card costs no queries. */
    private const PURCHASE_RELATIONS = ['store', 'city', 'buyer', 'items.product.images'];

    /**
     * Ratings this buyer has already left, fetched once for the whole history
     * rather than per order — the page renders every order at once, so a
     * per-order lookup would be a query per card.
     *
     * Keyed by product id, which is also the grain of the unique index: one
     * opinion per buyer per piece, spanning every order it appeared in. A row
     * here is what closes the form — see ReviewService.
     *
     * @return Collection<int, ProductReview>
     */
    private function productReviews(User $user): Collection
    {
        return ProductReview::where('user_id', $user->id)
            ->get()
            ->keyBy('product_id');
    }

    /**
     * One checkout — however many workshops it reached — as one card.
     *
     * @param  Collection<int, Order>  $group
     * @return array<string, mixed>
     */
    private function shapePurchase(Collection $group, Collection $productReviews): array
    {
        $first = $group->first();

        /*
         * The delivery fee belongs to the basket, not to a shop, and
         * CheckoutService parks the whole of it on whichever order it wrote
         * first. Summing across the group is what puts it back — and is why
         * the buyer's total here can exceed any single order's total_amount.
         */
        $itemsTotal = (float) $group->sum(fn (Order $order) => (float) $order->total_amount);
        $deliveryFee = (float) $group->sum(fn (Order $order) => (float) $order->delivery_fee);

        return [
            /*
             * The group reference when there is one, so the buyer quotes the
             * same string the admin desk sees for the whole purchase. A lone
             * legacy order still quotes its own.
             */
            'reference' => $first->group_reference ?: $first->reference,
            'status' => $this->statusFor($group),
            'created_at' => $first->created_at,
            'items_total' => $itemsTotal,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $itemsTotal + $deliveryFee,
            'shipping_address' => $first->shipping_address,
            'city' => $first->city?->name,

            // Who placed it, as the card's byline reads — the account's name,
            // which is not necessarily the recipient on the parcel.
            'buyer_name' => trim(($first->buyer?->first_name ?? '').' '.($first->buyer?->last_name ?? '')) ?: null,

            // Distinct lines, not units: "2 × vase" is one piece on the card.
            'items_count' => $group->sum(fn (Order $order) => $order->items->count()),

            /*
             * When the last parcel arrived — only once every live shop has
             * delivered, since until then the purchase has not been, whatever
             * one workshop managed. Cancelled shops are ignored for the same
             * reason statusFor() ignores them.
             */
            'delivered_at' => $this->statusFor($group) === 'delivered'
                ? $group->reject(fn (Order $order) => $order->status === 'cancelled')->max('delivered_at')
                : null,

            // Rated per shop, so the card offers the form once per shop that
            // has actually arrived rather than once for the purchase.
            'can_review' => $group->contains(fn (Order $order) => $order->status === 'delivered'),

            /*
             * Delivered pieces still waiting for a rating — what the "Rate my
             * items" button counts. By product, matching the one-rating-per-
             * product rule, so a piece bought from two workshops counts once.
             */
            'review_open_count' => $group
                ->filter(fn (Order $order) => $order->status === 'delivered')
                ->flatMap(fn (Order $order) => $order->items->pluck('product_id'))
                ->filter()
                ->unique()
                ->reject(fn ($productId) => $productReviews->has($productId))
                ->count(),

            // Snapshotted at checkout; the same carrier for every workshop.
            'delivery_service_name' => $first->delivery_service_name,
            'tracking_numbers' => $group->pluck('tracking_number')->filter()->unique()->values()->all(),

            'orders' => $group
                ->map(fn (Order $order) => $this->shapeOrder($order, $productReviews))
                ->values()
                ->all(),
        ];
    }

    /**
     * Where a whole purchase has got to, from its per-shop orders.
     *
     * The least advanced shop sets the pace: with one parcel delivered and one
     * still pending, the purchase is pending — claiming "delivered" while
     * something is still coming is the reading that would mislead. A purchase
     * only reads as cancelled when every shop in it was.
     *
     * @param  Collection<int, Order>  $group
     */
    private function statusFor(Collection $group): string
    {
        $live = $group->reject(fn (Order $order) => $order->status === 'cancelled');

        if ($live->isEmpty()) {
            return 'cancelled';
        }

        return $live
            ->sortBy(fn (Order $order) => self::STATUS_RANK[$order->status] ?? 0)
            ->first()
            ->status;
    }

    /**
     * One shop's share of a purchase.
     *
     * @return array<string, mixed>
     */
    private function shapeOrder(Order $order, Collection $productReviews): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'tracking_number' => $order->tracking_number,
            'created_at' => $order->created_at,
            'delivered_at' => $order->delivered_at,
            'shipping_address' => $order->shipping_address,
            'city' => $order->city?->name,
            'store' => [
                'store_name' => $order->store?->store_name,
            ],

            /*
             * Whether the rating panel is offered at all. Mirrors
             * ReviewService::canReview — the buyer check is implicit here
             * since the query is already scoped to them, so this is only the
             * delivered half. The server re-checks both on submit; this just
             * decides what to draw.
             */
            'can_review' => $order->status === 'delivered',

            'items' => $order->items->map(function ($item) use ($productReviews) {
                $review = $item->product_id
                    ? $productReviews->get($item->product_id)
                    : null;

                return [
                    'id' => $item->id,
                    // The form posts ratings keyed by product, so the page
                    // needs the id rather than just the label.
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    // The purchase-time snapshot ("Rouge / M"), never the live
                    // variant — see OrderItem::variant().
                    'variant_label' => $item->variant_label,
                    'product' => [
                        'name' => $item->product?->name,
                        'slug' => $item->product?->slug,
                        'image' => $item->product?->images->first()?->url,
                    ],
                    /*
                     * Non-null closes this piece's stars for good — ratings
                     * are one-shot, so the panel renders what was said rather
                     * than an input. `can_review` above is still the order's
                     * gate; this is the per-piece one.
                     */
                    'review' => $review ? [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                    ] : null,
                ];
            })->all(),
        ];
    }

    /**
     * The buyer's saved pieces.
     *
     * Reads through Product rather than Wishlist so the cards get the same
     * shape (and the same audience-aware pricing) as every other listing, via
     * BuyerProductResource.
     *
     * @return array<int, mixed>
     */
    public function wishlist(User $user, Request $request): array
    {
        /*
         * storefront() rather than a plain whereIn: a piece can be wishlisted
         * and later archived, or have its price cleared by an admin. Showing
         * a card that can't be opened or bought would be worse than quietly
         * dropping it — the row stays, so it reappears if the product does.
         */
        $products = Product::storefront()
            ->whereIn('id', $user->wishlists()->select('product_id'))
            ->with(['store.city', 'category', 'images'])
            ->withCount('wishlists')
            ->latest('id')
            ->get();

        return BuyerProductResource::collection($products)->resolve($request);
    }

    /**
     * @return Collection<int, City>
     */
    public function cities()
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    /**
     * The buyer's own edits to their identity.
     *
     * Only the name, deliberately. The email is what a verification link and
     * every password reset is sent to, and the phone is what a courier calls
     * — both change the account's reachability, so for now they are shown but
     * not editable here. `fill()` therefore never sees either key: the form
     * request whitelists first_name and last_name alone, so a hand-crafted
     * POST cannot slip one through.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): void
    {
        $user->fill($data)->save();
    }
}
