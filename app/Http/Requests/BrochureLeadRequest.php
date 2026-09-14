<?php

namespace App\Http\Requests;

use App\Models\BrochureLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrochureLeadRequest extends FormRequest
{
    /**
     * Errors go in their own bag, for the same reason
     * `NewsletterSubscriptionRequest` uses one: Inertia hands a page a single
     * flat `errors` object, and this form shares the homepage with the footer
     * signup — both have a field called `email`. Left in the default bag, a
     * rejected address here would light up the footer's input as well. The
     * dialog passes the matching `errorBag` on its way out.
     */
    protected $errorBag = 'brochure';

    /** Public by design: asking for the brochure is open to any visitor. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],

            /*
             * `email:rfc` rather than the DNS-checking variant: `dns` makes a
             * live MX lookup on every submission, which hands a public,
             * unauthenticated endpoint a network round trip it can be made to
             * repeat, and rejects perfectly good addresses whenever the
             * resolver has a bad minute. Same call as the newsletter form.
             */
            'email' => ['required', 'string', 'email:rfc', 'max:255'],

            'type' => ['required', Rule::in(BrochureLead::TYPES)],

            /*
             * Required only when they said they sell — `required_if` rather
             * than `nullable`, because a seller who never opened the select is
             * the one case this form must not accept silently. Buyers may send
             * it as long as it is empty; the service drops it either way.
             */
            'seller_type' => [
                'nullable',
                'required_if:type,seller',
                Rule::in(BrochureLead::SELLER_TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => __('welcome.brochure.dialog.invalid'),
            'email.required' => __('welcome.brochure.dialog.invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('welcome.brochure.dialog.first_name'),
            'last_name' => __('welcome.brochure.dialog.last_name'),
            'email' => __('welcome.brochure.dialog.email'),
            'type' => __('welcome.brochure.dialog.type'),
            'seller_type' => __('welcome.brochure.dialog.seller_type'),
        ];
    }
}
