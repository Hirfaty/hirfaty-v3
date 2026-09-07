<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trade access granted. Sent by ProfessionalAccountService::approve(), after
 * the role and verification stamp are already committed — so by the time this
 * lands, the wholesale prices it promises are genuinely live for them.
 */
class ProfessionalRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => 'Votre compte professionnel est activé — Hirfaty',
            'ar' => 'تم تفعيل حسابك المهني — حرفتي',
            'en' => 'Your professional account is active — Hirfaty',
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.professional.approved.{$locale}", [
                'user' => $notifiable,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
