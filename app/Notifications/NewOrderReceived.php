<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class NewOrderReceived extends Notification implements ShouldQueue
{
    use Queueable;
    
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $itemsList = $this->order->items
            ->map(fn($item) => "- {$item->product->name} × {$item->quantity} — {$item->subtotal} MAD")
            ->join("\n");

        return (new MailMessage)
            ->subject("New order #{$this->order->id} received — Hirfaty")
            ->greeting("Hello {$notifiable->first_name}!")
            ->line("You have received a new order on your store **{$this->order->store->store_name}**.")
            ->line("**Order #:** {$this->order->id}")
            ->line("**Buyer:** {$this->order->buyer->first_name} {$this->order->buyer->last_name}")
            ->line("**Items:**\n{$itemsList}")
            ->line("**Total:** {$this->order->total_amount} MAD")
            ->line("**Your earnings:** {$this->order->seller_earnings} MAD")
            ->line("**Shipping to:** {$this->order->shipping_address}")
            ->action('View order', url("/seller/orders/{$this->order->id}"))
            ->line('Please confirm the order as soon as possible.');
    }
}
