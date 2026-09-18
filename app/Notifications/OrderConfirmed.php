<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => "Votre commande {$this->order->reference} a été confirmée — Hirfaty",
            'ar' => "تم قبول طلبك {$this->order->reference} — حرفتي",
            'en' => "Your order {$this->order->reference} has been confirmed — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.confirmed.{$locale}", [
                'order' => $this->order,
                'user' => $notifiable,
            ]);
    }
}
