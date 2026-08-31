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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your order {$this->order->reference} has been confirmed — Hirfaty")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("Great news! Your order {$this->order->reference} has been confirmed by the seller.")
            ->line("**Store:** {$this->order->store->store_name}")
            ->line("**Total:** {$this->order->total_amount} MAD")
            ->line("**Shipping address:** {$this->order->shipping_address}")
            ->line('The seller is now preparing your order. You will receive another email once it is shipped.')
            ->action('View your order', url("/orders/{$this->order->reference}"))
            ->line('Thank you for shopping on Hirfaty!');
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
