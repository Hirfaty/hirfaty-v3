<?php

namespace App\Http\Requests\Buyer;

use App\Models\Address;
use App\Models\Order;
use App\Services\DeliveryFeeService;
use App\Services\PaymentService;
use App\Services\PayPalPaymentService;
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
 * `first_name`, `last_name` and `shipping_phone` are required down both
 * branches, because none of them can be assumed any more: signup now collects
 * only a password (see RegistrationCodeService::complete), so a buyer reaching
 * checkout may genuinely have no name on file, and a saved address may predate
 * the phone being mandatory. All three are pre-filled where known and
 * re-submitted every time rather than read off the account.
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Every payment method checkout knows: card through Payzone, or PayPal.
     * Hirfaty takes payment online, before anything ships (see
     * PaymentService). Cash on delivery is gone — orders that used it before
     * keep `cod` as history.
     */
    public const PAYMENT_METHODS = Order::ONLINE_METHODS;

    /**
     * The methods checkout accepts right now: each one only while its
     * provider's credentials are set. With neither, checkout is closed rather
     * than creating orders nobody can pay for.
     *
     * @return array<int, string>
     */
    public static function paymentMethods(): array
    {
        return array_values(array_filter([
            app(PaymentService::class)->cardAvailable() ? Order::PAYMENT_CARD : null,
            app(PayPalPaymentService::class)->available() ? Order::PAYMENT_PAYPAL : null,
        ]));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $deliveryFees = app(DeliveryFeeService::class);
        $cityIds = $deliveryFees->deliverableCityIds();

        return [
            // ── Who the parcel is for ────────────────────────────────
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:30'],

            /*
             * Scoped to the buyer's own book. Without the scope a crafted
             * request could ship an order to a stranger's saved address by
             * guessing an id — and that address would be snapshotted onto the
             * order, leaking where someone else lives.
             */
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('user_id', $this->user()->id)
                    // Only destinations the rate card still serves — an
                    // address abroad, or in a closed city, has no price.
                    ->where(fn ($query) => $deliveryFees->scopeToDeliverable($query)),
            ],

            // ── Where it goes, when they typed it fresh ──────────────
            'shipping_address' => ['required_without:address_id', 'nullable', 'string', 'max:500'],
            /*
             * Required for Morocco, where delivery is priced city by city and
             * `cities` is the list Amana quotes against — and meaningless
             * anywhere else, since that table holds Moroccan cities only. An
             * international town rides in the free-text `shipping_address`.
             */
            'city_id' => [
                'required_if:country,'.Address::DEFAULT_COUNTRY,
                'exclude_unless:country,'.Address::DEFAULT_COUNTRY,
                'nullable',
                // Just the cities on the rate card (any, on a card with none).
                $cityIds === null ? 'exists:cities,id' : Rule::in($cityIds),
            ],
            'postal_code' => ['required_without:address_id', 'nullable', 'string', 'max:20'],
            /*
             * Whitelisted against the rate cards' own countries — every active
             * carrier's together — rather than being free text or a hardcoded
             * constant: a destination with no rate on file is one we have no
             * price for and therefore do not ship to, so opening a new country
             * is a data change (see the delivery rate seeders) rather than a
             * code change.
             */
            'country' => [
                'required_without:address_id',
                'nullable',
                Rule::in($deliveryFees->shippableCountryCodes()),
            ],

            'payment_method' => ['required', Rule::in(self::paymentMethods())],

            /*
             * Which carrier ships the basket. Optional: without it the
             * service that ships to the destination is used (Amana in
             * Morocco, DHL abroad — DeliveryFeeService::serviceFor), so older
             * clients that never sent the field keep working. When it is
             * present it must name an active service — otherwise a crafted id
             * could pick a deactivated carrier — and one that ships to the
             * destination, which CheckoutService checks once the destination
             * is known (a saved address carries its own).
             */
            'delivery_service_id' => [
                'nullable',
                'integer',
                Rule::exists('delivery_services', 'id')->where('is_active', true),
            ],

            // Offered only on the "new address" branch; ignored otherwise.
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['nullable', 'string', 'max:60'],

            'notes' => ['nullable', 'string', 'max:500'],

            // Checking out the piece "Buy now" holds, rather than the cart.
            'buy_now' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('buyer.checkout.err_first_name'),
            'last_name.required' => __('buyer.checkout.err_last_name'),
            'shipping_address.required_without' => __('buyer.checkout.err_address'),
            'city_id.required_if' => __('buyer.checkout.err_city'),
            'city_id.exists' => __('buyer.checkout.err_city'),
            'city_id.in' => __('buyer.checkout.err_city'),
            'postal_code.required_without' => __('buyer.checkout.err_postal_code'),
            'country.required_without' => __('buyer.checkout.err_country'),
            'country.in' => __('buyer.checkout.err_country'),
            'shipping_phone.required' => __('buyer.checkout.err_phone'),
            'payment_method.required' => __('buyer.checkout.err_payment_method'),
            'payment_method.in' => __('buyer.checkout.err_payment_method'),
        ];
    }
}
