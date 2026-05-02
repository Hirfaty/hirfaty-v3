<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Illuminate\Validation\Rule;

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
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role'       => ['required', Rule::in(['seller', 'buyer'])],
            'password' => $this->passwordRules(),
        ])->validate();


        return User::create([
            // 'name' => $input['name'],
            'first_name' => $input["first_name"],
            "last_name" => $input["last_name"],
            'email' => $input['email'],
            'role' => $input["role"],
            'password' => $input['password'],
        ]);
    }
}
