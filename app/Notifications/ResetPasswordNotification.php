<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// Queued like every other user-facing notification here — sending mail
// synchronously blocks the request on the SMTP round-trip (and, if the mail
// host is unreachable, on PHP's execution time limit).
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => 'Réinitialisez votre mot de passe — Hirfaty',
            'ar' => 'إعادة تعيين كلمة المرور — حرفتي',
            'en' => 'Reset your password — Hirfaty',
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.auth.reset-password.{$locale}", [
                'url' => $url,
                'user' => $notifiable,
            ]);
    }
}
