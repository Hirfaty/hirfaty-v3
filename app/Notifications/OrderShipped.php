<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
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
            'fr' => "Votre commande {$this->order->reference} est en route — Hirfaty",
            'ar' => "طلبك {$this->order->reference} في الطريق إليك — حرفتي",
            'en' => "Your order {$this->order->reference} is on its way — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.shipped.{$locale}", [
                'order' => $this->order,
                'user' => $notifiable,
            ]);
    }
}
