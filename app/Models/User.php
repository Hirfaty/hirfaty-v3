<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\BuyerVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SellerVerifyEmail;
use App\Support\Contact;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as AuthMustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone_number
 * @property CarbonImmutable|null $phone_verified_at
 * @property string $role
 * @property int|null $created_by
 * @property CarbonImmutable|null $onboarded_at
 * @property CarbonImmutable|null $professional_verified_at
 * @property string|null $professional_request_status
 * @property string|null $professional_request_message
 * @property CarbonImmutable|null $professional_requested_at
 * @property CarbonImmutable|null $professional_reviewed_at
 * @property int|null $professional_reviewed_by
 * @property string|null $professional_decline_reason
 * @property string|null $avatar
 * @property string|null $bio
 * @property int $is_banned
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $two_factor_enabled
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsBanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
// `email_verified_at` is fillable so admin-created users can be pre-verified.
// Safe because no public endpoint mass-assigns raw request input into User —
// registration (Actions\Fortify\CreateNewUser) whitelists fields explicitly.
//
// `onboarded_at` is fillable (harmless: it only silences an intro tour).
// The professional_* columns are left over from the retired trade-account
// review: nothing writes them any more, and they decide nothing — prices
// follow the buyer's own choice (PriceModeService). Kept for the records they
// already hold.
//
// `phone_verified_at` is fillable for the same reason as `email_verified_at`:
// the code flows (RegistrationCodeService) stamp it when an SMS code proves
// the number, and they whitelist what they write.
#[Fillable(['email', 'contact_email', 'password', 'first_name', 'last_name', 'phone_number', 'phone_verified_at', 'role', 'bio', 'terms_accepted', 'terms_accepted_at', 'locale', 'email_verified_at', 'onboarded_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements AuthMustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    // Notifiable::notify is aliased rather than overridden outright: the trait
    // is used by this class, not inherited, so parent::notify() would resolve
    // to Authenticatable and fatal. See notify() below.
    use HasFactory, Notifiable {
        Notifiable::notify as notifyViaTrait;
    }
    use TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'professional_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'professional_requested_at' => 'datetime',
            'professional_reviewed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Craft categories picked during onboarding. A preference signal only —
     * nothing gates on it, and skipping the tour leaves it empty.
     */
    public function interests()
    {
        return $this->belongsToMany(Category::class);
    }

    /** The admin who opened this account by hand; null when they signed up themselves. */
    public function creator()
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * The desk's private notes about this account. Admin-only — read through
     * UserNoteService, never loaded onto a User the account holder can see.
     */
    public function adminNotes()
    {
        return $this->hasMany(UserNote::class);
    }

    /** Google / Facebook identities linked to this account. */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Saved delivery addresses, default first so `->first()` is the one
     * checkout would pre-fill.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->oldest();
    }

    /** The address checkout pre-fills, or null if the book is empty. */
    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Whether the post-verification intro still needs showing.
     *
     * Sellers are excluded: their first run is the store-setup wizard, which
     * already does this job and is mandatory rather than skippable.
     */
    public function needsOnboarding(): bool
    {
        return $this->onboarded_at === null && $this->isBuyer();
    }

    // Role helpers
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * True for both buyer kinds — use this for "can shop at all" checks.
     */
    public function isBuyer(): bool
    {
        return in_array($this->role, ['buyer', 'professional_buyer'], true);
    }

    /**
     * The `professional_buyer` role an admin can still give an account. A
     * label only: prices follow the buyer's own choice (PriceModeService).
     */
    public function isProfessionalBuyer(): bool
    {
        return $this->role === 'professional_buyer';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Admin-created accounts may have no email yet (see the users.email
     * nullable migration), and a shopper who signed up with a phone number
     * has none at all. Most notifications are mail-only, and dispatching one
     * to an account with no address queues a job that can only do nothing.
     *
     * So it is dropped when the account has neither an address nor a
     * verified number. With a verified number it goes through: the buyer's
     * order updates carry an SMS version (NotifiesBuyer), and a mail-only one
     * is skipped by the mail channel itself, which sends nothing without a
     * route. The admin bell (database channel) does not come through here: it
     * is sent with the Notification facade by AdminNotificationService, so an
     * admin with no address still gets it.
     */
    public function notify($instance): void
    {
        if ($this->email === null && $this->routeNotificationForSms() === null) {
            return;
        }

        $this->notifyViaTrait($instance);
    }

    /**
     * Where an SMS goes: the number, only once a code sent to it has been
     * typed back. `phone_number` is otherwise free text — a typo in it must
     * not send someone else this buyer's order updates.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone_verified_at !== null && filled($this->phone_number)
            ? $this->phone_number
            : null;
    }

    /**
     * "Verified" means a code proved one way of reaching them — the address
     * *or* the number. A shopper who signed up by phone has no address to
     * verify, and the `verified` middleware on the buyer routes must not lock
     * them out of their own orders for it.
     */
    #[Override]
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null || $this->phone_verified_at !== null;
    }

    /** Whether this account can sign in with a password at all. */
    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    /**
     * How they are reached, for showing back to them: the address when there
     * is one, else the verified number.
     */
    public function contact(): ?Contact
    {
        if (filled($this->email)) {
            return Contact::email($this->email);
        }

        return $this->routeNotificationForSms() !== null
            ? Contact::phone($this->phone_number)
            : null;
    }

    /**
     * An address to write to: the sign-in one, else the one a buyer who
     * signed in by phone typed at checkout (`contact_email`). For the desk
     * and exports only — the typed one was never confirmed, so it opens
     * nothing and order mail is not routed to it (see NotifiesBuyer).
     */
    public function reachableEmail(): ?string
    {
        return filled($this->email) ? $this->email : ($this->contact_email ?: null);
    }

    public function sendEmailVerificationNotification()
    {
        switch ($this->role) {
            case 'seller':
                $this->notify(new SellerVerifyEmail);
                break;
            case 'buyer':
                $this->notify(new BuyerVerifyEmail);
                break;
            default:
                $this->notify(new BuyerVerifyEmail);
        }
    }

    #[Override]
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }

    /**
     * A seller's products, reached through their store. Lets the admin list
     * query a per-user product count with a single withCount('products')
     * instead of a subquery or an N+1 loop.
     */
    public function products()
    {
        return $this->hasManyThrough(Product::class, Store::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }
}
