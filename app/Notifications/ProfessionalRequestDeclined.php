<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trade access refused. Carries the admin's reason: a buyer told only "no"
 * has nothing to act on, and the account may ask again once whatever was
 * missing is sorted out.
 */
class ProfessionalRequestDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $subjects = [
            'fr' => 'Votre demande de compte professionnel — Hirfaty',
            'ar' => 'طلب حسابك المهني — حرفتي',
            'en' => 'Your professional account request — Hirfaty',
        ];

        return (new MailMessage)
            ->subject($subjects[$locale] ?? $subjects['fr'])
            ->view("emails.buyer.professional.declined.{$locale}", [
                'user' => $notifiable,
                'reason' => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
