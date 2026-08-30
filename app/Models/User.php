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
 * @property CarbonImmutable|null $professional_verified_at
 * @property string|null $avatar
 * @property string|null $bio
 * @property int $is_banned
 * @property string $email
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
#[Fillable(['email', 'password', 'first_name', 'last_name', 'phone_number', 'role', 'terms_accepted', 'terms_accepted_at', 'locale', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements AuthMustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
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
