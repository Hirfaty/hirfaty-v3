<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * "Sign in with Google / Facebook".
 *
 * Every decision that matters lives here rather than in the controller (see
 * CLAUDE.md), and three of them are security decisions worth reading before
 * changing anything:
 *
 *  1. **The whitelist is a constant, not the URL.** `PROVIDERS` is what the
 *     route is validated against, so `/auth/anything/redirect` cannot reach
 *     Socialite and ask it to resolve a driver we never configured.
 *
 *  2. **An existing account is only ever joined on a *verified* email.** This
 *     is the account-takeover boundary: if a provider would hand us an address
 *     its owner never proved, then anyone able to type that address into that
 *     provider could walk into the matching Hirfaty account. See
 *     `assertsEmailIsVerified()`.
 *
 *  3. **The role is hardcoded to buyer.** Exactly as
 *     RegistrationCodeService::complete does. Selling is gated behind /sell and
 *     a workshop that an admin reviews; a social button must not be a shortcut
 *     around that.
 */
class SocialAuthService
{
    /**
     * The only providers this app will talk to.
     *
     * Adding one here is not enough on its own — it also needs a block in
     * config/services.php and a verified-email story in
     * `assertsEmailIsVerified()` below.
     */
    public const PROVIDERS = ['google', 'facebook'];

    /** Whether the route may proceed at all. */
    public function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    /**
     * Whether this provider has credentials configured.
     *
     * Drives which buttons the login screen offers. A button whose provider
     * has no client id would bounce the visitor to an OAuth error page, which
     * is a worse answer than not offering it.
     */
    public function isConfigured(string $provider): bool
    {
        return $this->isSupported($provider)
            && filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"));
    }

    /** Every provider ready to be shown on the sign-in screen. */
    public function configuredProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider) => $this->isConfigured($provider),
        ));
    }

    /**
     * Turn a provider's answer into a Hirfaty account, creating or linking as
     * needed.
     *
     * Returns null when the provider gave us nothing usable — no email, or an
     * email it will not vouch for. The caller turns that into a message rather
     * than a session; see SocialAuthController.
     */
    public function resolveUser(string $provider, SocialiteUser $socialUser): ?User
    {
        $providerId = $socialUser->getId();

        if (blank($providerId)) {
            return null;
        }

        /*
         * Match on the provider's id first, and on nothing else. This is the
         * returning-user path, and it deliberately survives the person changing
         * their email at Google: the subject id is stable, the address is not.
         */
        $existingLink = SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingLink?->user) {
            return $existingLink->user;
        }

        $email = $socialUser->getEmail();

        // Nothing to match on and nothing to create with: `users.email` is how
        // every other part of this app identifies a person. A Facebook account
        // with no email reaches here.
        if (blank($email)) {
            return null;
        }

        // The provider must vouch for the address before it can be used either
        // to join an existing account *or* to open a pre-verified new one.
        if (! $this->assertsEmailIsVerified($provider, $socialUser)) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($provider, $providerId, $email, $socialUser) {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first()
                ?? $this->createFrom($email, $socialUser);

            // Remembered so the next sign-in takes the id path above and no
            // longer depends on the address staying put.
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerId,
            ]);

            return $user;
        });
    }

    /**
     * Does this provider actually assert the address belongs to the person?
     *
     * The two differ, and the difference is not cosmetic:
     *
     *  - **Google** returns an explicit `email_verified` claim, so it is read
     *    and required. Google will happily issue a profile for an unverified
     *    address, so skipping this check would be the takeover hole.
     *
     *  - **Facebook** has no such flag. It does not need one: the Graph API
     *    only ever returns `email` for an address the account has confirmed,
     *    and omits the field entirely otherwise — so "an email is present" is
     *    Facebook's assertion, and the blank() guard in the caller is what
     *    enforces it.
     *
     * A provider added to PROVIDERS without a case here falls through to
     * false, which fails closed. That is deliberate: a new provider should have
     * to state its story rather than inherit somebody else's.
     */
    private function assertsEmailIsVerified(string $provider, SocialiteUser $socialUser): bool
    {
        return match ($provider) {
            'google' => ($socialUser->user['email_verified'] ?? false) === true,
            'facebook' => true,
            default => false,
        };
    }

    /**
     * A brand-new shopper, from whatever the provider was willing to tell us.
     */
    private function createFrom(string $email, SocialiteUser $socialUser): User
    {
        [$firstName, $lastName] = $this->splitName($socialUser);

        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            /*
             * Already proved, by the check above — the same reasoning that lets
             * the emailed-code flow stamp this without a second round trip.
             * Leaving it null would park a social sign-in on the "check your
             * inbox" wall it has already cleared.
             */
            'email_verified_at' => now(),
            /*
             * Unusable by construction: 64 random characters that are hashed
             * immediately and never shown to anyone. The column is NOT NULL and
             * this account signs in through the provider, so there is no
             * password to set — "forgot password" is how they get one if they
             * ever want to stop using the button.
             */
            'password' => Str::random(64),
            // Never anything else. Selling is gated behind /sell and an admin
            // review; see the class docblock.
            'role' => 'buyer',
            /*
             * Accepting the terms is implicit in completing a signup, and the
             * button on the screen says so. Stamped rather than left null so
             * these accounts are not indistinguishable from ones that dodged
             * the checkbox.
             */
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'locale' => app()->getLocale(),
            // phone_number is left null on purpose: it is nullable, the
            // providers do not supply one, and inventing a placeholder would
            // collide on the unique index the moment two people signed up.
        ]);
    }

    /**
     * Best-effort first/last name.
     *
     * Both columns are NOT NULL, and providers are inconsistent: Google sends
     * given/family names, Facebook often sends only a single display string.
     * Splitting on the last space keeps multi-word given names intact
     * ("Marie Claire Benali" → "Marie Claire" + "Benali"), and a one-word name
     * falls back to an em dash rather than an empty string so the account is
     * still addressable on screen.
     *
     * @return array{0: string, 1: string}
     */
    private function splitName(SocialiteUser $socialUser): array
    {
        $given = $socialUser->user['given_name'] ?? null;
        $family = $socialUser->user['family_name'] ?? null;

        if (filled($given)) {
            return [$given, filled($family) ? $family : '—'];
        }

        $full = trim((string) ($socialUser->getName() ?? ''));

        if (blank($full)) {
            // Nothing at all to go on — fall back to the local part of the
            // address, which is at least recognisably theirs.
            $local = Str::before((string) $socialUser->getEmail(), '@');

            return [$local !== '' ? $local : 'Hirfaty', '—'];
        }

        if (! str_contains($full, ' ')) {
            return [$full, '—'];
        }

        return [Str::beforeLast($full, ' '), Str::afterLast($full, ' ')];
    }
}
