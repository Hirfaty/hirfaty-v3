<?php

namespace App\Services;

use App\Http\Resources\BuyerProductResource;
use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
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
     * The buyer's order history, flattened for the orders page.
     *
     * Unpaginated on purpose: checkout splits a basket into one order per
     * store, so even an active buyer's list stays short.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orders(User $user): array
    {
        return Order::where('buyer_id', $user->id)
            ->with(['store', 'city', 'items.product.images'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
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
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'product' => [
                        'name' => $item->product?->name,
                        'slug' => $item->product?->slug,
                        'image' => $item->product?->images->first()?->url,
                    ],
                ])->all(),
            ])
            ->all();
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
