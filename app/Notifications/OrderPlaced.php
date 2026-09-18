<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One checkout, one confirmation — even when it produced several orders.
 *
 * CheckoutService splits a basket into one Order per store, but the buyer
 * placed a single order from their own point of view: sending one of these
 * per store used to leave a multi-seller purchase looking, in the inbox, like
 * only its last store's items had gone through — nothing tied the separate
 * emails back together into the one thing the buyer actually bought.
 */
class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order the checkout produced, with
     *                              `store` and `items.product` loaded.
     */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        // Every order in one checkout shares this — the identifier the buyer
        // sees for the purchase as a whole, rather than any one store's own
        // order number.
        $reference = $this->orders->first()?->group_reference;

        $subjects = [
            'fr' => "Votre commande {$reference} est confirmée — Hirfaty",
            'ar' => "تم تأكيد طلبك {$reference} — حرفتي",
            'en' => "Your order {$reference} is confirmed — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-placed.{$locale}", [
                'orders' => $this->orders,
                'reference' => $reference,
                'user' => $notifiable,
            ]);
    }
}
