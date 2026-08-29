<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => "Votre commande #{$this->order->id} est confirmée — Hirfaty",
            'ar' => "تم تأكيد طلبك #{$this->order->id} — حرفتي",
            'en' => "Your order #{$this->order->id} is confirmed — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-placed.{$locale}", [
                'order' => $this->order,
                'user' => $notifiable,
            ]);
    }
}
