<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SellerVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $locale = $notifiable->locale ?? 'fr';
        $subjects = [
            'fr' => 'Vérifiez votre adresse email — Hirfaty',
            'ar' => 'تحقق من بريدك الإلكتروني — حرفتي',
            'en' => 'Verify your email address — Hirfaty',
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.seller.verify.{$locale}", [
                'url'  => $url,
                'user' => $notifiable,
            ]);
    }
}
