<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells Hirfaty's onboarding inbox an artisan has just signed up on /sell, so
 * someone can call them and file their first listings.
 *
 * Addressed on-demand to `config('hirfaty.seller_signup_email')`, a role
 * inbox rather than a User — and French only for the same reason as
 * NewPurchaseForAdmin: there is no notifiable whose locale we could read.
 */
class NewSellerForAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  User  $seller  With `store.category` and `store.city` loaded.
     */
    public function __construct(public User $seller) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sellerName = trim(($this->seller->first_name ?? '').' '.($this->seller->last_name ?? ''));
        $sellerName = $sellerName !== '' ? $sellerName : $this->seller->email;
        $storeName = $this->seller->store?->store_name;

        return (new MailMessage)
            ->subject('Nouvel artisan inscrit — '.($storeName ?? $sellerName))
            ->view('emails.admin.seller.new', [
                'seller' => $this->seller,
                'sellerName' => $sellerName,
                'store' => $this->seller->store,
            ]);
    }
}
