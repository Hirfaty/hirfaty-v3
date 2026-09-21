<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One parcel shipping, or every workshop's parcel in the same checkout
 * shipping together — either way, one email.
 *
 * Hirfaty's desk collects a purchase from every workshop it touched and
 * hands the whole thing to the carrier as one parcel under one tracking
 * number (see Admin\OrderController's docblock), so the group case is the
 * common one here, not the exception. See OrderConfirmed's docblock for why
 * this takes a Collection rather than a single Order.
 */
class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order that just moved to `shipped`
     *                              in this pass, with `store` and
     *                              `items.product` loaded.
     */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';
        $first = $this->orders->first();
        $reference = $first->group_reference ?? $first->reference;

        $subjects = [
            'fr' => "Votre commande {$reference} est en route — Hirfaty",
            'ar' => "طلبك {$reference} في الطريق إليك — حرفتي",
            'en' => "Your order {$reference} is on its way — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.shipped.{$locale}", [
                'orders' => $this->orders,
                'reference' => $reference,
                'user' => $notifiable,
            ]);
    }
}
