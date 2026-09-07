<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Placing an order.
 *
 * The buyer either picks an address out of their book or types a new one, so
 * the two halves are conditionally required on each other rather than both
 * being mandatory — requiring the free-text fields outright would make a saved
 * address unusable, and requiring neither would let an order through with
 * nowhere to ship it.
 *
 * `shipping_phone` is required in both branches. Amana rings the buyer to
 * deliver, and a saved address may predate the phone being mandatory (the
 * column is nullable and older rows have none), so the number is always taken
 * from the form — pre-filled from the chosen address, but never assumed.
 */
class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * Scoped to the buyer's own book. Without the scope a crafted
             * request could ship an order to a stranger's saved address by
             * guessing an id — and that address would be snapshotted onto the
             * order, leaking where someone else lives.
             */
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],

            'shipping_address' => ['required_without:address_id', 'nullable', 'string', 'max:500'],
            'city_id' => ['required_without:address_id', 'nullable', 'exists:cities,id'],

            'shipping_phone' => ['required', 'string', 'max:30'],

            // Offered only on the "new address" branch; ignored otherwise.
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['nullable', 'string', 'max:60'],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_address.required_without' => __('buyer.checkout.err_address'),
            'city_id.required_without' => __('buyer.checkout.err_city'),
            'city_id.exists' => __('buyer.checkout.err_city'),
            'shipping_phone.required' => __('buyer.checkout.err_phone'),
        ];
    }
}
