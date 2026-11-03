<?php

namespace App\Services\Cathedis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cathedis's tracker API ("Documentation API Cathedis", v9.0.3, and the
 * Postman collection "Client Tracker API V5.5"), as far as Hirfaty uses it.
 * Like PayPalClient, nothing here touches our own rows — CathedisCitySyncService
 * and CathedisShipmentService decide what the answers mean.
 *
 * Every answer carries `status`: 0 is success, -1 an error, even under an
 * HTTP 200. Everything but the city list needs a session: `/login.jsp` answers
 * with a JSESSIONID cookie, sent back on every other call. The session is
 * cached and opened again once when Cathedis no longer accepts it.
 */
class CathedisClient
{
    /** The model Cathedis's print actions expect alongside the delivery ids. */
    private const DELIVERY_MODEL = 'com.tracker.delivery.db.Delivery';

    /** The fields read back from a delivery — see fetchDelivery(). */
    private const DELIVERY_FIELDS = [
        'id', 'nomOrder', 'amount', 'city.name', 'sector.name',
        'deliveryStatus', 'deliveryStatus.type', 'status',
    ];

    /** Kept a little under Tomcat's default 30-minute idle timeout. */
    private const SESSION_MINUTES = 20;

    public function baseUrl(): string
    {
        return rtrim((string) config('services.cathedis.url'), '/');
    }

    /** Creating deliveries is offered only once the account's login is set. */
    public function isConfigured(): bool
    {
        return filled(config('services.cathedis.username'))
            && filled(config('services.cathedis.password'));
    }

    /**
     * Every city Cathedis knows, with its aliases and sectors — public, no
     * login. Those Cathedis does not deliver to are included
     * (`deliveryAvailability` false), so a sync can tell them apart.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CathedisException
     */
    public function cities(): array
    {
        try {
            $response = $this->http()->get('/ws/public/c2c/city');
        } catch (ConnectionException $e) {
            throw new CathedisException('Cathedis could not be reached: '.$e->getMessage());
        }

        $body = (array) ($response->json() ?? []);
        $cities = $body['data'] ?? null;

        // An empty list is refused too: syncing it would archive every city.
        if (! $response->successful() || ($body['status'] ?? null) !== 0 || ! is_array($cities) || $cities === []) {
            throw new CathedisException(
                'Cathedis answered '.$response->status().' without a city list.',
                status: $response->status(),
            );
        }

        return array_values($cities);
    }

    /**
     * Create a delivery ("delivery.api.save"). Cathedis files it as
     * "En Attente Ramassage" and answers with its id.
     *
     * @param  array<string, string>  $delivery  The fields Cathedis documents (recipient, city, sector, phone, amount…).
     * @return array<string, mixed> The delivery as Cathedis saved it, `id` included.
     *
     * @throws CathedisException
     */
    public function createDelivery(array $delivery): array
    {
        $saved = $this->action('delivery.api.save', ['delivery' => $delivery])['values']['delivery'] ?? null;

        if (! is_array($saved) || empty($saved['id'])) {
            throw new CathedisException('Cathedis saved the delivery without giving its id.');
        }

        return $saved;
    }

    /**
     * One delivery as Cathedis has it now: `deliveryStatus` carries the status
     * name and its type (PENDING, SHIPPED, REJECTED, STANDBY), `status` whether
     * the cash it collected has been paid back (UNPAID / PAID).
     *
     * @return array<string, mixed>
     *
     * @throws CathedisException
     */
    public function fetchDelivery(int $id): array
    {
        $body = $this->authenticated(fn (PendingRequest $http) => $http->post(
            "/ws/rest/com.tracker.delivery.db.Delivery/{$id}/fetch",
            ['fields' => self::DELIVERY_FIELDS],
        ));

        $delivery = $body['data'][0] ?? null;

        if (! is_array($delivery)) {
            throw new CathedisException("Cathedis has no delivery {$id}.");
        }

        return $delivery;
    }

    /**
     * The printable delivery slip for some deliveries, as PDF bytes: A5
     * ("delivery.print.bl") or a 10×10 cm label for a thermal printer
     * ("delivery.print.bl4x4"). Cathedis renders it and answers with the
     * file's path, fetched in a second call.
     *
     * @param  list<int>  $ids
     *
     * @throws CathedisException
     */
    public function label(array $ids, bool $thermal = false): string
    {
        $path = $this->action($thermal ? 'delivery.print.bl4x4' : 'delivery.print.bl', [
            '_ids' => array_values($ids),
            '_model' => self::DELIVERY_MODEL,
        ])['view']['views'][0]['name'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new CathedisException('Cathedis generated no delivery slip.');
        }

        $response = $this->send(fn (PendingRequest $http) => $http->accept('*/*')->get('/'.ltrim($path, '/')));

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'pdf')) {
            throw new CathedisException('Cathedis did not hand over the delivery slip.', status: $response->status());
        }

        return $response->body();
    }

    /**
     * Ask Cathedis to come and collect ("action-refresh-pickup-request"):
     * every delivery still "En Attente Ramassage" joins the request, and the
     * answer names the agent who will come.
     *
     * With a pickup point configured, the request names it and the deliveries
     * — the Postman collection's form; without one, the bare action the
     * documentation shows, where Cathedis uses the account's own.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed> The pickup request (`name`, `agent`, `deliveries`…).
     *
     * @throws CathedisException
     */
    public function requestPickup(array $ids = []): array
    {
        $point = config('services.cathedis.pickup_point_id');

        $context = filled($point)
            ? ['ids' => array_values($ids), 'pickupPointId' => (int) $point]
            : [];

        return $this->action('action-refresh-pickup-request', $context, 'com.tracker.pickup.db.PickupRequest')['values']['pickupRequest'] ?? [];
    }

    /**
     * Run one of Cathedis's `/ws/action` actions and return the first entry of
     * its `data`.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws CathedisException
     */
    private function action(string $action, array $context = [], ?string $model = null): array
    {
        $payload = array_filter([
            'action' => $action,
            'model' => $model,
            'data' => $context === [] ? null : ['context' => $context],
        ], fn ($value) => $value !== null);

        $body = $this->authenticated(fn (PendingRequest $http) => $http->post('/ws/action', $payload));

        return (array) ($body['data'][0] ?? []);
    }

    /**
     * Send a call with the session cookie and read its JSON, opening a new
     * session once if Cathedis no longer accepts the cached one.
     *
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws CathedisException
     */
    private function authenticated(callable $call): array
    {
        $response = $this->send($call);

        if ($this->sessionRefused($response)) {
            Cache::forget($this->sessionCacheKey());
            $response = $this->send($call);
        }

        if ($this->sessionRefused($response)) {
            throw new CathedisException('Cathedis refused the session.', status: $response->status());
        }

        $body = (array) $response->json();

        if (! $response->successful() || ($body['status'] ?? null) !== 0) {
            throw new CathedisException('Cathedis refused: '.$this->errorMessage($response, $body), status: $response->status());
        }

        return $body;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws CathedisException
     */
    private function send(callable $call): Response
    {
        try {
            return $call($this->http()->withoutRedirecting()->withHeaders([
                'Cookie' => 'JSESSIONID='.$this->session(),
            ]));
        } catch (ConnectionException $e) {
            throw new CathedisException('Cathedis could not be reached: '.$e->getMessage());
        }
    }

    /** Signed out: a 401/403, a redirect to the login page, or that page itself. */
    private function sessionRefused(Response $response): bool
    {
        return in_array($response->status(), [401, 403], true)
            || $response->redirect()
            || ($response->successful() && $response->json() === null && ! str_contains((string) $response->header('Content-Type'), 'pdf'));
    }

    /**
     * The session cookie, logging in when none is cached.
     *
     * @throws CathedisException
     */
    private function session(): string
    {
        if (! $this->isConfigured()) {
            throw new CathedisException('The Cathedis login is not set (CATHEDIS_USERNAME / CATHEDIS_PASSWORD).');
        }

        return Cache::remember($this->sessionCacheKey(), now()->addMinutes(self::SESSION_MINUTES), function () {
            try {
                $response = $this->http()->withoutRedirecting()->post('/login.jsp', [
                    'username' => config('services.cathedis.username'),
                    'password' => config('services.cathedis.password'),
                ]);
            } catch (ConnectionException $e) {
                throw new CathedisException('Cathedis could not be reached: '.$e->getMessage());
            }

            foreach ((array) ($response->headers()['Set-Cookie'] ?? []) as $cookie) {
                if (preg_match('/JSESSIONID=([^;]+)/', (string) $cookie, $m)) {
                    return $m[1];
                }
            }

            throw new CathedisException('Cathedis refused the login.', status: $response->status());
        });
    }

    /** Per account, so changing the login never reuses another's session. */
    private function sessionCacheKey(): string
    {
        return 'cathedis.session.'.md5($this->baseUrl().'|'.config('services.cathedis.username'));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(Response $response, array $body): string
    {
        $data = $body['data'] ?? null;
        $message = is_array($data)
            ? ($data['message'] ?? $data['title'] ?? $data[0]['message'] ?? null)
            : (is_string($data) ? $data : null);

        return Str::limit(strip_tags((string) ($message ?? $response->body() ?: 'HTTP '.$response->status())), 300);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
