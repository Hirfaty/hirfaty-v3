<?php

namespace App\Services\PayPal;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * PayPal's REST API (Orders v2), as far as Hirfaty uses it.
 *
 * Opening a PayPal order for one payment attempt, capturing it once the buyer
 * has approved it, reading it back, and checking that a webhook really came
 * from PayPal. Like PayzoneClient, nothing here touches orders —
 * PayPalPaymentService decides what PayPal's answers mean for a purchase.
 */
class PayPalClient
{
    /**
     * The currencies PayPal can charge, among those written with two
     * decimals. MAD, AED and SAR are not on PayPal's list; the zero-decimal
     * ones it does take (JPY, HUF, TWD) are left out because every amount
     * here is sent with cents.
     */
    public const CURRENCIES = [
        'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD',
        'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD',
    ];

    /** The app's locales, as PayPal's checkout names them. */
    private const LOCALES = [
        'fr' => 'fr-FR',
        'ar' => 'ar-EG',
        'en' => 'en-US',
    ];

    /** The headers PayPal signs a webhook delivery with. */
    private const WEBHOOK_HEADERS = [
        'auth_algo' => 'PAYPAL-AUTH-ALGO',
        'cert_url' => 'PAYPAL-CERT-URL',
        'transmission_id' => 'PAYPAL-TRANSMISSION-ID',
        'transmission_sig' => 'PAYPAL-TRANSMISSION-SIG',
        'transmission_time' => 'PAYPAL-TRANSMISSION-TIME',
    ];

    /** PayPal is offered only when both credentials are present. */
    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'));
    }

    public function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Open the PayPal order one attempt is paid through, and return its id
     * and the page that asks the buyer to approve it.
     *
     * The attempt's charge id rides along as `custom_id` (copied onto every
     * capture, so a webhook can be traced back to its row) and as
     * `invoice_id`, which PayPal refuses to see paid twice.
     *
     * @return array{id: string, status: string, approve_url: string}
     *
     * @throws PayPalException
     */
    public function createOrder(Payment $payment, string $description, string $locale, string $returnUrl, string $cancelUrl): array
    {
        $body = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['PayPal-Request-Id' => 'create-'.$payment->charge_id])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $payment->group_reference,
                    'custom_id' => $payment->charge_id,
                    'invoice_id' => $payment->charge_id,
                    'description' => mb_substr($description, 0, 127),
                    'amount' => [
                        'currency_code' => $payment->currency,
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'brand_name' => 'Hirfaty',
                            'locale' => self::LOCALES[$locale] ?? 'fr-FR',
                            // The delivery address is Hirfaty's, taken at
                            // checkout — PayPal must not offer to change it.
                            'shipping_preference' => 'NO_SHIPPING',
                            'user_action' => 'PAY_NOW',
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'return_url' => $returnUrl,
                            'cancel_url' => $cancelUrl,
                        ],
                    ],
                ],
            ]));

        $approve = collect($body['links'] ?? [])
            ->first(fn ($link) => in_array($link['rel'] ?? null, ['payer-action', 'approve'], true));

        if (empty($body['id']) || empty($approve['href'])) {
            throw new PayPalException('PayPal opened an order without an approval link.', body: $body);
        }

        return [
            'id' => (string) $body['id'],
            'status' => (string) ($body['status'] ?? ''),
            'approve_url' => (string) $approve['href'],
        ];
    }

    /**
     * Take the money for an order the buyer has approved.
     *
     * `$requestId` makes the call idempotent: the buyer's return and the
     * APPROVED webhook may both try, and PayPal answers the second with the
     * first's result instead of charging twice.
     *
     * @return array<string, mixed> the order, captures included
     *
     * @throws PayPalException
     */
    public function captureOrder(string $orderId, string $requestId): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
                'Prefer' => 'return=representation',
            ])
            ->withBody('{}', 'application/json')
            ->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture'));
    }

    /**
     * Give back some or all of a capture. `$requestId` makes it idempotent, so
     * a retried request never refunds twice.
     *
     * @return array<string, mixed> the refund (`status`: COMPLETED, PENDING…)
     *
     * @throws PayPalException
     */
    public function refundCapture(string $captureId, float $amount, string $currency, string $requestId, ?string $noteToPayer = null): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
                'Prefer' => 'return=representation',
            ])
            ->post('/v2/payments/captures/'.rawurlencode($captureId).'/refund', array_filter([
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $currency,
                ],
                'note_to_payer' => $noteToPayer !== null ? mb_substr($noteToPayer, 0, 255) : null,
            ])));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PayPalException
     */
    public function showOrder(string $orderId): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->get('/v2/checkout/orders/'.rawurlencode($orderId)));
    }

    /**
     * Whether a webhook delivery was signed by PayPal for our webhook.
     *
     * Asked of PayPal itself (verify-webhook-signature). The event goes back
     * byte for byte as it arrived: decoding and re-encoding it would change
     * its escaping and the signature would never match.
     */
    public function verifyWebhook(string $rawBody, HeaderBag $headers): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (blank($webhookId) || ! $this->isConfigured()) {
            return false;
        }

        // Spliced in raw below, so it must be one JSON object and nothing more.
        if (! is_array(json_decode($rawBody, true))) {
            return false;
        }

        $fields = [];

        foreach (self::WEBHOOK_HEADERS as $field => $header) {
            $value = $headers->get($header);

            if (blank($value)) {
                return false;
            }

            $fields[$field] = (string) $value;
        }

        $fields['webhook_id'] = (string) $webhookId;

        $payload = substr(json_encode($fields, JSON_UNESCAPED_SLASHES), 0, -1)
            .',"webhook_event":'.$rawBody.'}';

        try {
            $body = $this->send(fn (PendingRequest $http) => $http
                ->withBody($payload, 'application/json')
                ->post('/v1/notifications/verify-webhook-signature'));
        } catch (PayPalException) {
            return false;
        }

        return ($body['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * One authenticated call, its JSON body on success.
     *
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws PayPalException
     */
    private function send(callable $call): array
    {
        try {
            $response = $call($this->http()->withToken($this->accessToken()));
        } catch (ConnectionException $e) {
            throw new PayPalException('PayPal could not be reached: '.$e->getMessage());
        }

        $body = (array) ($response->json() ?? []);

        if ($response->successful()) {
            return $body;
        }

        // Credentials rotated since the token was cached: start fresh next time.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        throw new PayPalException(
            'PayPal answered '.$response->status().': '.($body['message'] ?? $body['name'] ?? 'error'),
            status: $response->status(),
            issue: $body['details'][0]['issue'] ?? $body['name'] ?? null,
            body: $body,
        );
    }

    /**
     * An OAuth token for the API, cached until shortly before PayPal expires
     * it (about nine hours) — fetching one per call would double every
     * request.
     *
     * @throws PayPalException
     */
    private function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()
                ->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.client_secret'))
                ->asForm()
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $e) {
            throw new PayPalException('PayPal could not be reached: '.$e->getMessage());
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new PayPalException(
                'PayPal refused the credentials ('.$response->status().').',
                status: $response->status(),
                issue: $response->json('error'),
            );
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    /** Per mode and client, so switching sandbox → live never reuses a token. */
    private function tokenCacheKey(): string
    {
        return 'paypal.token.'.md5(config('services.paypal.mode').'|'.config('services.paypal.client_id'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
