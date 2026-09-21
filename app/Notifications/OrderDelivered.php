<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One parcel delivered, or every workshop's parcel in the same purchase
 * delivered together — either way, one email. See OrderConfirmed's docblock
 * for why this takes a Collection rather than a single Order.
 */
class OrderDelivered extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order that just moved to
     *                              `delivered` in this pass, with `store`
     *                              and `items.product` loaded.
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
            'fr' => "Votre commande {$reference} a été livrée — Hirfaty",
            'ar' => "تم توصيل طلبك {$reference} — حرفتي",
            'en' => "Your order {$reference} has been delivered — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.delivered.{$locale}", [
                'orders' => $this->orders,
                'reference' => $reference,
                'user' => $notifiable,
            ]);
    }
}
