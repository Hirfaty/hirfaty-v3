<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One order cancelled, or every order in the same purchase cancelled
 * together — either way, one email. See OrderConfirmed's docblock for why
 * this takes a Collection rather than a single Order.
 */
class OrderCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order that just moved to
     *                              `cancelled` in this pass, with `store`
     *                              and `items.product` loaded.
     */
    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';
        $first = $this->orders->first();
        $reference = $first->group_reference ?? $first->reference;

        $subjects = [
            'fr' => "Votre commande {$reference} a été annulée — Hirfaty",
            'ar' => "تم إلغاء طلبك {$reference} — حرفتي",
            'en' => "Your order {$reference} has been cancelled — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.cancelled.{$locale}", [
                'orders' => $this->orders,
                'reference' => $reference,
                'user' => $notifiable,
            ]);
    }
}
