<?php

namespace App\Services;

use App\Concerns\GeneratesEmailCodes;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\RegistrationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * The signup flow: email → emailed code → details → account.
 *
 * Serves both doors. The first three steps are identical whichever one the
 * visitor came through; only the last differs, which is why there are two
 * completions — `complete()` for a shopper, `completeSeller()` for an artisan
 * who also names a workshop. Which one runs is decided by the intent recorded
 * on the pending row, not by anything the browser sends at the end.
 *
 * Everything that decides *what the flow does* lives here, so
 * RegistrationController stays a set of thin request/redirect adapters (see
 * CLAUDE.md — business logic belongs in a service, not a controller).
 *
 * The one invariant worth stating out loud: a `PendingRegistration` is never
 * resumable from its email alone. Resumption always requires the secret in the
 * resume cookie, because otherwise typing a stranger's pending address would
 * hand you their verified step and let you finish their signup with your own
 * password. See `resolve()`.
 */
class RegistrationCodeService
{
    /*
     * Code generation, hashing, the attempt ceiling and the cooldown all live
     * in the shared concern, so this service and LoginCodeService cannot end
     * up enforcing different rules for what is the same kind of secret.
     */
    use GeneratesEmailCodes;

    /**
     * Name of the cookie carrying the resume token.
     *
     * Prefixed like the guest cart's localStorage key (`hirfaty_guest_cart`)
     * so the app's client-side storage keys read consistently.
     */
    public const COOKIE = 'hirfaty_pending_registration';

    /**
     * Shop creation is delegated rather than reimplemented: the slug rule and
     * the "is this worth an admin's time yet" decision already live in
     * StoreSetupService, and a second copy here would drift from the one
     * Admin\UserController uses.
     */
    public function __construct(
        private readonly StoreSetupService $stores,
    ) {}

    /**
     * Begin (or restart) a signup for this address and mail out a code.
     *
     * Returns the plaintext resume token, which the caller must queue onto the
     * response as a cookie — it is not stored anywhere else in readable form.
     *
     * `$intent` records which door this signup came through, and is rewritten
     * on every call for the same reason `verified_at` is cleared below: someone
     * who abandoned a shopper signup and came back through /sell means the
     * second thing, not the first.
     */
    public function start(
        string $email,
        string $locale,
        string $intent = PendingRegistration::INTENT_BUYER,
    ): string {
        $code = $this->generateCode();
        $resumeToken = Str::random(48);

        /*
         * updateOrCreate, keyed on the email, because `pending_registrations`
         * carries a UNIQUE index on it: someone who submits the form twice (a
         * double-click, or a genuine "let me try that again") should refresh
         * the one row rather than collide on the insert.
         *
         * `verified_at` resets to null deliberately. A brand-new code means a
         * brand-new proof of inbox access, so any previously passed step is
         * void — otherwise re-submitting an address that is already pending
         * and verified would skip the code entirely.
         */
        $pending = PendingRegistration::updateOrCreate(
            ['email' => $email],
            [
                ...$this->freshCodeAttributes(Hash::make($code)),
                'resume_token_hash' => hash('sha256', $resumeToken),
                'verified_at' => null,
                'locale' => $locale,
                'intent' => $intent,
            ],
        );

        $this->mail($pending, $code);

        return $resumeToken;
    }

    /**
     * Mail a fresh code for a signup already in progress.
     *
     * Silently does nothing while the cooldown is running: the controller has
     * already told the visitor how long is left, and a resend that quietly
     * succeeds is friendlier than an error page for a button they were told
     * not to press yet.
     */
    public function resend(PendingRegistration $pending): bool
    {
        if ($this->secondsUntilResend($pending) > 0) {
            return false;
        }

        $code = $this->generateCode();

        $pending->update($this->freshCodeAttributes(Hash::make($code)));

        $this->mail($pending, $code);

        return true;
    }

    /**
     * Check a typed code, counting the attempt either way.
     *
     * Returns false for an expired or exhausted code as well as a wrong one;
     * the caller distinguishes those cases with `isLocked()`/`hasExpired()` to
     * pick the right message.
     */
    public function verify(PendingRegistration $pending, string $code): bool
    {
        if ($pending->hasExpired() || $this->isLocked($pending)) {
            return false;
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->increment('attempts');

            return false;
        }

        $pending->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Create the account and clear the pending row.
     *
     * Deliberately fast: a shopper's signup is just a password on top of the
     * address the code already confirmed. `first_name`/`last_name`/
     * `phone_number` are nullable on `users` for exactly this — nothing here
     * blocks on a name or a delivery number, and Buyer\ProfileController on
     * /account is where either gets filled in whenever the buyer gets to it.
     * A checkout still asks for a delivery contact of its own, so nothing
     * downstream depends on these being set at signup.
     *
     * `email_verified_at` is stamped here rather than left for the emailed
     * link flow: typing back a code sent to that inbox *is* the proof of
     * ownership, and asking for it twice would be the same check again.
     *
     * @param  array{first_name?: ?string, last_name?: ?string, phone_number?: ?string, password: string}  $data
     */
    public function complete(PendingRegistration $pending, array $data): User
    {
        $user = User::create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'password' => $data['password'],
            'email' => $pending->email,
            'email_verified_at' => now(),
            // Only ever a buyer. Sellers register through the separate form,
            // which still runs Fortify's CreateNewUser — self-assigning any
            // other role must stay impossible from a public endpoint.
            'role' => 'buyer',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'locale' => $pending->locale,
        ]);

        $pending->delete();

        return $user;
    }

    /**
     * Create the artisan's account *and* their workshop, then clear the row.
     *
     * One call rather than "make the account, then send them to a setup
     * wizard": a seller who abandons that second screen is an account that can
     * reach no /seller route at all, which is the dead end the wizard existed
     * to paper over. Collecting the shop on the same form removes the gap.
     *
     * The role is taken from `$pending->intent`, never from the submitted
     * data — that is the line that keeps `seller` from being self-assignable
     * by anyone who can craft a POST. The intent was fixed when the code was
     * sent, and proving inbox access is what stands behind it.
     *
     * Nothing here makes the shop sellable. StoreSetupService files it as
     * `pending` with `status = inactive`, so it waits on an admin exactly like
     * one made through the old wizard — hence the holding page the seller
     * lands on afterwards.
     *
     * @param  array{first_name: string, last_name: string, phone_number: string, password: string, store_name: string, type: string, category_id: int, city_id: int, description: string}  $data
     */
    public function completeSeller(PendingRegistration $pending, array $data): User
    {
        return DB::transaction(function () use ($pending, $data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone_number' => $data['phone_number'],
                'password' => $data['password'],
                'email' => $pending->email,
                // Typing back a code sent to that inbox is the proof of
                // ownership; asking for it again via an emailed link would be
                // the same check twice. Same reasoning as complete().
                'email_verified_at' => now(),
                'role' => 'seller',
                'terms_accepted' => true,
                'terms_accepted_at' => now(),
                'locale' => $pending->locale,
            ]);

            $this->stores->create($user, [
                'store_name' => $data['store_name'],
                'type' => $data['type'],
                'category_id' => $data['category_id'],
                'city_id' => $data['city_id'],
                'description' => $data['description'],
            ]);

            $pending->delete();

            return $user;
        });
    }

    /**
     * The signup this browser is allowed to resume, or null.
     *
     * Deliberately keyed on the cookie's secret and nothing else — see the
     * class docblock. An expired row counts as "nothing in progress": its code
     * is dead, so resuming onto the code screen would be a dead end.
     */
    public function resolve(Request $request): ?PendingRegistration
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $pending = PendingRegistration::firstWhere(
            'resume_token_hash',
            hash('sha256', $token),
        );

        if (! $pending || $pending->hasExpired()) {
            return null;
        }

        return $pending;
    }

    /** The cookie to queue onto a response so this browser can resume. */
    public function resumeCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: self::COOKIE,
            value: $token,
            // Outlives the code on purpose. Once the code is spent the row
            // holds `verified_at`, and this cookie is the only thing that
            // lets the visitor walk back in and finish the details step.
            minutes: 60 * 24 * 7,
            httpOnly: true,
        );
    }

    /** Drop the resume cookie — the flow is finished or being abandoned. */
    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(self::COOKIE);
    }

    /**
     * On-demand mail: there is no `User` to notify yet, which is the whole
     * point of this table.
     */
    private function mail(PendingRegistration $pending, string $code): void
    {
        Notification::route('mail', $pending->email)->notify(
            new RegistrationCode($code, $pending->locale, self::CODE_TTL_MINUTES),
        );
    }
}
