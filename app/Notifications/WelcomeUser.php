<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUser extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $plainPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Hirfaty account is ready')
            ->greeting("Hello {$notifiable->first_name}!")
            ->line('An account has been created for you on Hirfaty.')
            ->line('Here are your login credentials:')
            ->line("**Email:** {$notifiable->email}")
            ->line("**Password:** {$this->plainPassword}")
            ->action('Login to your account', url('/login'))
            ->line('For security, please change your password after your first login.')
            ->line('Never share your password with anyone.');
    }
}
