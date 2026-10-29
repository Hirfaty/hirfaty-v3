<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\Request;

/**
 * The buyer's delivery addresses, managed from /account.
 *
 * That page edits the default address in place, so store() only runs for a
 * buyer who has none yet — AddressService makes the first one default either
 * way. makeDefault() and destroy() serve the fuller address book.
 *
 * Every action taking an {address} re-checks ownership: route-model binding
 * will happily resolve another buyer's id, so without it anyone could re-point
 * or delete someone else's delivery address by guessing a number.
 */
class AddressController extends Controller
{
    public function __construct(private AddressService $addresses) {}

    public function store(Request $request)
    {
        $data = $this->validated($request, ['is_default' => ['nullable', 'boolean']]);

        $this->addresses->create($request->user(), $data);

        return back()->with('success', __('buyer.account.address_saved'));
    }

    public function update(Request $request, Address $address)
    {
        $this->authoriseOwnership($request, $address);

        $this->addresses->update($address, $this->validated($request));

        return back()->with('success', __('buyer.account.address_saved'));
    }

    public function makeDefault(Request $request, Address $address)
    {
        $this->authoriseOwnership($request, $address);

        $this->addresses->makeDefault($request->user(), $address);

        return back()->with('success', __('buyer.account.address_default_saved'));
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authoriseOwnership($request, $address);

        $this->addresses->delete($request->user(), $address);

        return back()->with('success', __('buyer.account.address_deleted'));
    }

    /**
     * Shared between store() and update() so the two can't drift into
     * accepting different addresses.
     *
     * @param  array<string, array<int, string>>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            /*
             * Required, though the column stays nullable: the courier rings the
             * buyer to deliver, so an address with no number is one nobody can
             * ship to. Leaving the column nullable keeps the addresses saved
             * before this rule readable — checkout asks for the number again
             * on those rather than refusing them.
             */
            'recipient_phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:500'],
            'city_id' => ['required', 'exists:cities,id'],
            /*
             * Optional, unlike at checkout: this form also edits addresses
             * saved before the column existed, and forcing a code to re-save an
             * otherwise untouched address would be asking the buyer to invent
             * one. Kept loose at 20 rather than pinned to Morocco's 5 digits so
             * the same field still works if Hirfaty ever ships elsewhere.
             */
            'postal_code' => ['nullable', 'string', 'max:20'],
            ...$extra,
        ], [
            'address_line.required' => __('buyer.account.err_address_line'),
            'recipient_phone.required' => __('buyer.account.err_phone'),
            'city_id.required' => __('buyer.account.err_city'),
            'city_id.exists' => __('buyer.account.err_city'),
        ]);
    }

    private function authoriseOwnership(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }
}
