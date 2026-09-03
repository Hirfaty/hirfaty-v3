<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {

        Validator::make($input, [
            ...$this->profileRules(),
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // professional_buyer is a self-serve choice at signup (an
            // "individual / professional" toggle on the buyer flow) — it is
            // not admin-gated, see User::isProfessionalVerified().
            'role' => ['required', Rule::in(['seller', 'buyer', 'professional_buyer'])],
            /*
             * `unique` is load-bearing: users.phone_number carries a UNIQUE
             * index, so without it a duplicate number sailed past validation
             * and blew up on the insert — a 500 on the register screen
             * instead of "this number is already in use".
             */
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class, 'phone_number'),
            ],
            'password' => $this->passwordRules(),
            'terms_accepted' => ['required', 'accepted'],
        ])->validate();

        return User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'phone_number' => $input['phone_number'],
            'password' => $input['password'],
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'locale' => session('locale', 'fr'),
        ]);
    }
}
