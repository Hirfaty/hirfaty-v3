<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsVerificationMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BuyerVerifyEmail extends VerifyEmail
{
    use BuildsVerificationMail, Queueable;

    public function toMail($notifiable): MailMessage
    {
        return $this->verificationMail(
            $this->verificationUrl($notifiable),
            $notifiable,
            'buyer',
        );
    }
}
