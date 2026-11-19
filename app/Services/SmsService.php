<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Text messages: the one-time code for a phone sign-in, and order updates for
 * a buyer who gave a number instead of an address.
 *
 * Three drivers (config `services.sms.driver`):
 *
 *  - `log` writes the message to the log. The tests use it.
 *  - `messagepit` posts to the local Messagepit container (docker-compose.yml)
 *    — the same inbox that catches local mail — and the text shows in its SMS
 *    tab at http://localhost:8025. It speaks Twilio's API, so it exercises the
 *    very request production sends.
 *  - `twilio` sends through Twilio's Messages API — a plain form POST, so no
 *    SDK to install for one endpoint.
 *
 * In production only `twilio` delivers anything: `log` and `messagepit` would
 * accept a phone sign-in and never reach a phone, so `available()` is false
 * there and the screens refuse the number with a message, rather than leave
 * someone waiting on an SMS that is never coming.
 */
class SmsService
{
    /** The Twilio account placeholder Messagepit is addressed with. */
    private const MESSAGEPIT_ACCOUNT = 'AC00000000000000000000000000000000';

    public function available(): bool
    {
        return ! app()->isProduction() || $this->driver() === 'twilio';
    }

    /**
     * Send one message to an E.164 number.
     *
     * @throws RuntimeException when the provider refuses it, so a sign-in can
     *                          tell the visitor the code did not go
     */
    public function send(string $to, string $text): void
    {
        match ($this->driver()) {
            'twilio' => $this->viaTwilio($to, $text),
            'messagepit' => $this->viaMessagepit($to, $text),
            default => Log::info('SMS to '.$to.': '.$text),
        };
    }

    private function driver(): string
    {
        return (string) config('services.sms.driver', 'log');
    }

    private function viaTwilio(string $to, string $text): void
    {
        $config = config('services.sms.twilio');

        if (empty($config['sid']) || empty($config['token']) || empty($config['from'])) {
            throw new RuntimeException('Twilio is selected for SMS but TWILIO_SID, TWILIO_AUTH_TOKEN or TWILIO_FROM is missing.');
        }

        $this->postMessage('https://api.twilio.com', $config['sid'], $config['token'], $config['from'], $to, $text);
    }

    /**
     * Messagepit takes any account and token unless it was started with
     * MP_TWILIO_AUTH_TOKEN, so a placeholder pair is enough locally.
     */
    private function viaMessagepit(string $to, string $text): void
    {
        $config = config('services.sms.messagepit');

        try {
            $this->postMessage(
                rtrim((string) $config['url'], '/'),
                self::MESSAGEPIT_ACCOUNT,
                (string) ($config['token'] ?: 'messagepit'),
                (string) ($config['from'] ?: 'Hirfaty'),
                $to,
                $text,
            );
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Messagepit is not reachable at '.$config['url'].' — start it with `docker compose up -d messagepit`.',
                previous: $e,
            );
        }
    }

    /** Twilio's Messages API — the request Messagepit imitates. */
    private function postMessage(string $base, string $account, string $token, string $from, string $to, string $text): void
    {
        $response = Http::asForm()
            ->withBasicAuth($account, $token)
            ->timeout(15)
            ->post("{$base}/2010-04-01/Accounts/{$account}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The SMS was refused: '.($response->json('message') ?? $response->status()));
        }
    }
}
