<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\SmsChannel;

/**
 * A buyer's order update, by email — or by SMS for a buyer who signed up with
 * a phone number and has no address.
 *
 * One or the other, never both: the text is a short pointer to the order's
 * tracking page, and a buyer with an inbox already gets the full email.
 *
 * The using notification carries `$orders` (every order of one checkout) and
 * names its message with smsKey().
 */
trait NotifiesBuyer
{
    abstract protected function smsKey(): string;

    public function via(object $notifiable): array
    {
        if (filled($notifiable->email ?? null)) {
            return ['mail'];
        }

        if (method_exists($notifiable, 'routeNotificationForSms') && $notifiable->routeNotificationForSms() !== null) {
            return [SmsChannel::class];
        }

        return ['mail'];
    }

    public function toSms(object $notifiable): string
    {
        $first = $this->orders->first();
        $reference = $first->group_reference ?? $first->reference;

        return __($this->smsKey(), [
            'reference' => $reference,
            'url' => route('buyer.purchases.tracking', $reference),
        ], $notifiable->locale ?? 'fr');
    }
}
