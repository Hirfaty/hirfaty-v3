<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A buyer editing their own identity from /account.
 *
 * Name only. The email and phone are rendered read-only on that page, and
 * whitelisting just these two here is what actually enforces it — validated()
 * is all the service fills, so adding `email` to a hand-crafted POST changes
 * nothing.
 *
 * Distinct from Settings\ProfileUpdateRequest, which edits the email and is
 * used by the shared /settings/profile screen.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('buyer.account.err_first_name'),
            'last_name.required' => __('buyer.account.err_last_name'),
        ];
    }
}
