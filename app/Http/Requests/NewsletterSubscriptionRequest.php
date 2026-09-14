<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NewsletterSubscriptionRequest extends FormRequest
{
    /**
     * Errors go in their own bag, because this form sits in the footer of
     * *every* page. Left in the default bag, a rejected address here would
     * light up the `email` field of whatever other form happens to be on
     * screen — login, checkout, the seller's settings — since Inertia shares
     * one flat `errors` object per page and those fields are all named
     * `email`. The footer form passes the matching `errorBag` on its way out.
     */
    protected $errorBag = 'newsletter';

    /** Public by design: subscribing is the one thing a stranger may do here. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /*
             * `email:rfc` rather than the DNS-checking variant: `dns` makes a
             * live MX lookup on every submission, which hands a public,
             * unauthenticated endpoint a network round trip it can be made to
             * repeat, and rejects perfectly good addresses whenever the
             * resolver has a bad minute.
             */
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('common.footer.newsletter.invalid'),
            'email.email' => __('common.footer.newsletter.invalid'),
            'email.max' => __('common.footer.newsletter.invalid'),
        ];
    }
}
