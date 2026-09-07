<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'role' => 'buyer',
            /*
             * Established account by default, mirroring email_verified_at:
             * a factory user stands in for someone already using the site, so
             * they shouldn't be dropped into the first-run tour. Tests about
             * onboarding itself opt in with ->notOnboarded().
             */
            'onboarded_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A brand-new buyer who has not yet seen (or skipped) the intro tour.
     */
    public function notOnboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarded_at' => null,
        ]);
    }

    /**
     * A trade account an admin has approved — the only kind that is billed
     * wholesale and held to bulk minimums.
     *
     * Set after creation rather than as state attributes: the professional_*
     * columns are deliberately not fillable (they decide which price list an
     * account pays), so a factory attribute would be silently dropped and the
     * test would quietly assert against a plain buyer.
     */
    public function verifiedProfessional(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'professional_buyer',
        ])->afterCreating(function (User $user) {
            $user->forceFill([
                'professional_verified_at' => now(),
                'professional_request_status' => User::PRO_REQUEST_APPROVED,
            ])->save();
        });
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
