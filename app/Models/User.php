<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\BuyerVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SellerVerifyEmail;
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
 * @property string $role
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
 * @property string $password
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
// The professional_* columns deliberately are NOT — they decide which price
// list an account is billed on, and are written only by
// ProfessionalAccountService via forceFill, never from request input.
#[Fillable(['email', 'password', 'first_name', 'last_name', 'phone_number', 'role', 'bio', 'terms_accepted', 'terms_accepted_at', 'locale', 'email_verified_at', 'onboarded_at'])]
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
            'professional_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'professional_requested_at' => 'datetime',
            'professional_reviewed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Trade access asked for, awaiting an admin. */
    public const PRO_REQUEST_PENDING = 'pending';

    /** Granted — the account is a verified professional_buyer. */
    public const PRO_REQUEST_APPROVED = 'approved';

    /** Refused, with a reason the buyer can read. */
    public const PRO_REQUEST_DECLINED = 'declined';

    /**
     * Craft categories picked during onboarding. A preference signal only —
     * nothing gates on it, and skipping the tour leaves it empty.
     */
    public function interests()
    {
        return $this->belongsToMany(Category::class);
    }

    /** The admin who approved or declined the trade request. */
    public function professionalReviewer()
    {
        return $this->belongsTo(self::class, 'professional_reviewed_by');
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

    /** Asked for trade access and still waiting on an admin. */
    public function hasPendingProfessionalRequest(): bool
    {
        return $this->professional_request_status === self::PRO_REQUEST_PENDING;
    }

    /**
     * Whether the trade-account form should be offered at all.
     *
     * False once there's a live request (nothing to add by asking twice) and
     * false for an account that is already professional. A *declined* request
     * does not block: circumstances change, and the refusal reason tells them
     * what to fix before asking again.
     */
    public function canRequestProfessional(): bool
    {
        return $this->isBuyer()
            && ! $this->isProfessionalBuyer()
            && ! $this->hasPendingProfessionalRequest();
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
     * Buys in bulk and is billed from the professional price list.
     */
    public function isProfessionalBuyer(): bool
    {
        return $this->role === 'professional_buyer';
    }

    /**
     * A trade account an admin has actually reviewed.
     *
     * Trade pricing keys off this rather than the role alone: anyone can
     * register as a professional buyer, and wholesale rates should not be
     * handed out — nor billed — on an unvetted self-declaration.
     */
    public function isVerifiedProfessionalBuyer(): bool
    {
        return $this->isProfessionalBuyer()
            && $this->professional_verified_at !== null;
    }

    /**
     * Whether an admin has reviewed and approved this professional buyer.
     * Nothing currently gates purchasing on this — it's surfaced to the admin
     * so they can decide to verify (or unverify) an account, not enforced.
     */
    public function isProfessionalVerified(): bool
    {
        return $this->professional_verified_at !== null;
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Admin-created accounts may have no email yet (see the users.email
     * nullable migration), and every notification in app/Notifications is
     * mail-only — dispatching one would queue a job that can only fail in the
     * worker with "no To address". A seller reaches this: an admin can list
     * products for them, so a buyer's order fires NewOrderReceived at an
     * account that has no address on file.
     *
     * Drop it instead. Nothing is lost that could have been delivered, and
     * the notification resumes working the moment an address is filled in.
     * Revisit if a database/SMS channel is ever added.
     */
    public function notify($instance): void
    {
        if ($this->email === null) {
            return;
        }

        $this->notifyViaTrait($instance);
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
