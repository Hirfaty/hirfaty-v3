<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One workshop confirming its share of a purchase, or every workshop in the
 * same checkout doing so together — either way, one email.
 *
 * `OrderStatusService::transitionMany()` passes whichever orders actually
 * moved to `confirmed` in one pass, so a three-workshop purchase confirmed as
 * a group is one email covering all three, not three separate ones (see
 * OrderPlaced's docblock for the same shape at checkout time).
 */
class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection  $orders  Every order that just moved to `confirmed`
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

        // What the buyer quotes for the purchase as a whole when there is
        // one; a legacy order placed before grouping existed falls back to
        // its own reference, same as Admin\OrderController::summariseGroup.
        $reference = $first->group_reference ?? $first->reference;

        $subjects = [
            'fr' => "Votre commande {$reference} a été confirmée — Hirfaty",
            'ar' => "تم قبول طلبك {$reference} — حرفتي",
            'en' => "Your order {$reference} has been confirmed — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.confirmed.{$locale}", [
                'orders' => $this->orders,
                'reference' => $reference,
                'user' => $notifiable,
            ]);
    }
}
