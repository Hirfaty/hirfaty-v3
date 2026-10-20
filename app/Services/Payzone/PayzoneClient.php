<?php

namespace App\Services\Payzone;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The Payzone Paywall, as its integration kit describes it.
 *
 * Building the signed form that sends a buyer to the hosted payment page,
 * checking that a notification really came from Payzone, and — through the
 * REST API, when its caller credentials are set — refunding a charge.
 * Nothing here touches orders — PaymentService and RefundService decide what
 * Payzone's answers mean for a purchase.
 */
class PayzoneClient
{
    /**
     * The app's locales, as the paywall names them. Arabic is offered as
     * ar_EG, the only Arabic locale on Payzone's list.
     */
    private const LOCALES = [
        'fr' => 'fr_FR',
        'ar' => 'ar_EG',
        'en' => 'en_US',
    ];

    /** Card payment is offered only when every credential is present. */
    public function isConfigured(): bool
    {
        return filled(config('services.payzone.merchant_account'))
            && filled(config('services.payzone.secret_key'))
            && filled(config('services.payzone.notification_key'))
            && filled(config('services.payzone.paywall_url'));
    }

    /**
     * The form fields that open the paywall for one payment.
     *
     * The timestamp is signed with the payload and Payzone honours it for 30
     * minutes, so this is built when the buyer is actually being redirected,
     * never ahead of time.
     *
     * @return array{url: string, payload: string, signature: string}
     */
    public function launchForm(Payment $payment, User $buyer, string $description, string $locale): array
    {
        $name = trim(($buyer->first_name ?? '').' '.($buyer->last_name ?? ''));

        $payload = array_filter([
            // Authentication
            'merchantAccount' => config('services.payzone.merchant_account'),
            'timestamp' => time(),
            'skin' => 'vps-1-vue',

            // Customer — the id must be stable per buyer (used for fraud checks).
            'customerId' => 'hirfaty-'.$buyer->id,
            'customerCountry' => 'MA',
            'customerLocale' => self::LOCALES[$locale] ?? 'fr_FR',
            'customerName' => $name !== '' ? $name : null,
            'customerEmail' => $buyer->email,

            // Charge — chargeId comes back as the notification's `id`.
            'chargeId' => $payment->charge_id,
            'orderId' => $payment->group_reference,
            'price' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'description' => mb_substr($description, 0, 256),

            // Deep linking
            'mode' => 'DEEP_LINK',
            'paymentMethod' => 'CREDIT_CARD',
            'showPaymentProfiles' => 'false',
            'callbackUrl' => $this->callbackUrl(),
            'successUrl' => route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'success']),
            'failureUrl' => route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'failure']),
            'cancelUrl' => route('payments.payzone.return', [$payment->charge_id, 'outcome' => 'cancel']),
        ], fn ($value) => $value !== null);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'url' => config('services.payzone.paywall_url'),
            'payload' => $json,
            'signature' => hash('sha256', config('services.payzone.secret_key').$json),
        ];
    }

    /**
     * Whether a notification body was signed with our notification key.
     *
     * Over the raw body exactly as received — re-encoding the decoded JSON
     * would change the bytes and never match.
     */
    public function verifyCallback(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, (string) config('services.payzone.notification_key'));

        return hash_equals($expected, strtolower(trim($signature)));
    }

    /**
     * Whether Hirfaty can refund a card itself: the paywall is set up and
     * Payzone has issued the API caller credentials.
     */
    public function canRefund(): bool
    {
        return $this->isConfigured()
            && filled(config('services.payzone.api_caller_name'))
            && filled(config('services.payzone.api_caller_password'));
    }

    /**
     * Give back some or all of a charge (the `REFUND` command), in the
     * charge's own currency.
     *
     * `$chargeId` is ours — the id the paywall was opened with, which is what
     * Payzone's API looks charges up by. Payzone takes no idempotency key, so
     * the caller must make sure a refund is asked for once (RefundService
     * locks the payment around this call).
     *
     * @return array<string, mixed> the charge, its transactions included
     *
     * @throws PayzoneException
     */
    public function refundCharge(string $chargeId, float $amount): array
    {
        $path = '/api/v3/charges/'.rawurlencode($chargeId);
        $body = (string) json_encode(['command' => 'REFUND', 'amount' => round($amount, 2)]);
        $timestamp = time();

        try {
            $response = Http::baseUrl($this->apiUrl())
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->withHeaders([
                    'X-MerchantAccount' => (string) config('services.payzone.merchant_account'),
                    'X-CallerName' => (string) config('services.payzone.api_caller_name'),
                    'X-HMAC-Timestamp' => (string) $timestamp,
                    'X-HMAC-Signature' => self::apiSignature(
                        (string) config('services.payzone.api_caller_name'),
                        (string) config('services.payzone.merchant_account'),
                        $timestamp,
                        $path,
                        $body,
                        (string) config('services.payzone.api_caller_password'),
                    ),
                ])
                // Sent byte for byte as signed.
                ->withBody($body, 'application/json')
                ->post($path);
        } catch (ConnectionException $e) {
            throw new PayzoneException('Payzone could not be reached: '.$e->getMessage());
        }

        $json = (array) ($response->json() ?? []);

        if (! $response->successful()) {
            throw new PayzoneException(
                'Payzone answered '.$response->status().': '.($json['message'] ?? 'error'),
                status: $response->status(),
                errorCode: $json['errorCode'] ?? null,
                body: $json,
            );
        }

        return $json;
    }

    /**
     * Payzone's API request signature: HMAC-SHA256, keyed with the caller's
     * password, over caller name + merchant account + timestamp + request
     * path + body, in hex (their "API authentication" section).
     */
    public static function apiSignature(string $callerName, string $merchantAccount, int $timestamp, string $path, string $body, string $password): string
    {
        return strtoupper(hash_hmac('sha256', $callerName.$merchantAccount.$timestamp.$path.$body, $password));
    }

    /** The API host: PAYZONE_API_URL, else the paywall's own host. */
    private function apiUrl(): string
    {
        $configured = config('services.payzone.api_url');

        if (filled($configured)) {
            return rtrim((string) $configured, '/');
        }

        $paywall = parse_url((string) config('services.payzone.paywall_url'));

        return ($paywall['scheme'] ?? 'https').'://'.($paywall['host'] ?? 'payment-sandbox.payzone.ma');
    }

    /**
     * Where Payzone posts its notifications: this app, or the tunnel set in
     * PAYZONE_CALLBACK_BASE_URL when working on a machine Payzone can't reach.
     */
    private function callbackUrl(): string
    {
        $base = config('services.payzone.callback_base_url');

        if (blank($base)) {
            return route('payments.payzone.callback');
        }

        return rtrim($base, '/').route('payments.payzone.callback', absolute: false);
    }
}
