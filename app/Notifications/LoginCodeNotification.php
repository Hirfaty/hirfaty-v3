<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code that signs an existing account in without its password.
 *
 * Unlike RegistrationCode this has a real `User` to notify, so the locale is
 * read off the notifiable rather than passed in — and it goes through
 * `$user->notify()`, which already declines to dispatch at an account with no
 * email address (see User::notify).
 *
 * Named `LoginCodeNotification`, not `LoginCode`, because App\Models\LoginCode
 * is the row this code was stored in; two classes one namespace apart sharing
 * a name is how imports end up silently wrong.
 */
class LoginCodeNotification extends Notification
{
    use Queueable;

    /** Locales with a translation file. Anything else falls back to French. */
    private const LOCALES = ['fr', 'en', 'ar'];

    /**
     * `$code` is public so tests can read the code that was actually mailed —
     * it is hashed in the database by design, so the notification is the only
     * place the plaintext exists.
     */
    public function __construct(
        public readonly string $code,
        private readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /*
         * Guarded the same way BuildsVerificationMail guards it: an unexpected
         * locale interpolated into a `__()` call fails inside the queued job,
         * which is silent — nothing surfaces a dead queue job to the person
         * sitting on the code screen waiting for it.
         */
        $locale = in_array($notifiable->locale, self::LOCALES, true)
            ? $notifiable->locale
            : 'fr';

        return (new MailMessage)
            // The code rides in the subject as well as the body: most people
            // can read it straight off the notification banner.
            ->subject(__('emails.login_code.subject', ['code' => $this->code], $locale))
            ->view('emails.auth-code', [
                'code' => $this->code,
                'locale' => $locale,
                'expiresInMinutes' => $this->expiresInMinutes,
                'copy' => 'login_code',
            ]);
    }
}
