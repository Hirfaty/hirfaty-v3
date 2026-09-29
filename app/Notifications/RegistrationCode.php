<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code that proves someone can read the inbox they signed up with.
 *
 * Sent on-demand — `Notification::route('mail', $email)->notify(...)` — because
 * at this point in the flow there is no `User` row to notify: the account is
 * only created once this code comes back. That also means none of `User`'s
 * niceties apply, so the locale is passed in explicitly rather than read off a
 * notifiable.
 */
class RegistrationCode extends Notification
{
    use Queueable;

    /** Locales with a translation file. Anything else falls back to French. */
    private const LOCALES = ['fr', 'en', 'ar'];

    /**
     * `$code` is public so tests can read the code that was actually mailed —
     * it is hashed in the database by design, so the notification is the only
     * place the plaintext exists, and reflecting into a private property to
     * get at it is worse than just saying it is readable.
     *
     * `$mailLocale`, not `$locale`: the parent Notification already declares a
     * non-readonly `$locale` (the one `->locale()` sets), and redeclaring it
     * readonly here is a *fatal compile error* rather than an override — which
     * takes the whole PHP process down, not just this class. Do not rename it
     * back.
     *
     * `$url` is the signed "confirm my email" link that rides alongside the
     * code (see RegistrationCodeService::resolveLink) — public for the same
     * reason as `$code`, so tests can follow the link that was mailed.
     */
    public function __construct(
        public readonly string $code,
        private readonly string $mailLocale,
        private readonly int $expiresInMinutes,
        public readonly ?string $url = null,
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
         * locale interpolated into a view or a `__()` call fails inside the
         * queued job, which is silent — nothing surfaces a dead queue job to
         * the person sitting on the code screen waiting.
         */
        $locale = in_array($this->mailLocale, self::LOCALES, true)
            ? $this->mailLocale
            : 'fr';

        return (new MailMessage)
            // The code rides in the subject as well as the body: most people
            // can read it straight off the notification banner and never open
            // the mail at all.
            ->subject(__('emails.registration_code.subject', ['code' => $this->code], $locale))
            ->view('emails.auth-code', [
                'code' => $this->code,
                'locale' => $locale,
                'expiresInMinutes' => $this->expiresInMinutes,
                'copy' => 'registration_code',
                'url' => $this->url,
            ]);
    }
}
