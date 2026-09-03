<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared assembly for the buyer and seller verification emails.
 *
 * Both used to carry their own copy of this, each pointing at its own set of
 * three locale templates — six near-identical files whose CSS had already
 * drifted apart. There is now one view, and the copy lives in
 * lang/<locale>/emails.php.
 */
trait BuildsVerificationMail
{
    /** Locales with a translation file. Anything else falls back to French. */
    private const LOCALES = ['fr', 'en', 'ar'];

    /**
     * @param  string  $audience  'buyer' or 'seller'
     */
    protected function verificationMail(string $url, object $notifiable, string $audience): MailMessage
    {
        /*
         * Guarded against the stored value: the old code interpolated
         * `$notifiable->locale` straight into a view name, so a user row
         * carrying anything but fr/en/ar threw "view not found" inside a
         * queued job — a silent failure, since nothing surfaces a dead queue
         * job to the person waiting for the email.
         */
        $locale = in_array($notifiable->locale, self::LOCALES, true)
            ? $notifiable->locale
            : 'fr';

        return (new MailMessage)
            ->subject(__("emails.verify.{$audience}.subject", [], $locale))
            ->view('emails.verify', [
                'url' => $url,
                'user' => $notifiable,
                'locale' => $locale,
                'audience' => $audience,
                // Read the way Illuminate\Auth\Notifications\VerifyEmail reads
                // it, so the stated expiry always matches the signed link's.
                'expiresInMinutes' => config('auth.verification.expire', 60),
            ]);
    }
}
