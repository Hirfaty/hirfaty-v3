<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
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
            ->subject("Your order #{$this->order->id} is on its way — Hirfaty")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("Your order #{$this->order->id} has been shipped via Amana.")
            ->line("**Store:** {$this->order->store->store_name}")
            ->line("**Tracking number:** {$this->order->tracking_number}")
            ->line("**Shipping address:** {$this->order->shipping_address}")
            ->action('Track your order on Amana', 'https://www.amana.ma')
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
