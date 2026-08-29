<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
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
