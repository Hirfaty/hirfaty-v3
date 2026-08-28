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

    /**
     * Create a new notification instance.
     */
    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => "Votre commande #{$this->order->id} est confirmée — Hirfaty",
            'ar' => "تم تأكيد طلبك #{$this->order->id} — حرفتي",
            'en' => "Your order #{$this->order->id} is confirmed — Hirfaty",
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.order-status.confirmed.{$locale}", [
                'order' => $this->order,
                'user'  => $notifiable,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
