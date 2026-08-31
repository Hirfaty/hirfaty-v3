<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledByBuyer extends Notification implements ShouldQueue
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
            'fr' => "Commande {$this->order->reference} annulée par l'acheteur — Hirfaty",
            'ar' => "تم إلغاء الطلب {$this->order->reference} من قِبل المشتري — حرفتي",
            'en' => "Order {$this->order->reference} cancelled by buyer — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.seller.order.cancelled-by-buyer.{$locale}", [
                'order' => $this->order,
                'user' => $notifiable,
            ]);
    }
}
