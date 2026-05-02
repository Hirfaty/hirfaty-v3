<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;


class SellerVerifyEmail extends VerifyEmail {
    
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your Hirfaty account')
            ->greeting('Welcome to Hirfaty! 🎉')
            ->line('You\'re almost ready to start selling. Please verify your email address.')
            ->action('Verify Email', $url)
            ->line('This link expires in 60 minutes.')
            ->line('If you didn\'t create an account, ignore this email.');
    }

}