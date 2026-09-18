<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells the Hirfaty desk a basket has been bought and needs fulfilling.
 *
 * Sent once per checkout, not once per workshop: a basket spanning three
 * artisans is still one job — collect from three benches, deliver to one
 * address — so three emails would misrepresent it as three.
 *
 * Addressed on-demand (`Notification::route('mail', …)`) rather than to a
 * User, because `config('hirfaty.admin_email')` is a role inbox that may not
 * belong to any account. French only for the same reason: it goes to one
 * internal desk, so there is no notifiable whose locale we could read.
 */
class NewPurchaseForAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order the checkout produced, with
     *                              `store`, `buyer` and `items.product.images` loaded.
     */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = $this->orders->first();
        $buyer = $first?->buyer;

        $buyerName = trim(($buyer?->first_name ?? '').' '.($buyer?->last_name ?? ''));
        $buyerName = $buyerName !== '' ? $buyerName : ($buyer?->email ?? 'Client');

        // The group reference is what the admin desk searches on, so it is
        // what the subject line carries — one purchase, one identifier.
        $reference = $first?->group_reference ?? $first?->reference ?? '';

        return (new MailMessage)
            ->subject("Nouvelle commande {$reference} — {$buyerName}")
            ->view('emails.admin.purchase.new', [
                'orders' => $this->orders,
                'buyer' => $buyer,
                'buyerName' => $buyerName,
                'reference' => $reference,
            ]);
    }
}
