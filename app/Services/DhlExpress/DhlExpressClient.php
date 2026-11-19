<?php

namespace App\Services\DhlExpress;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * DHL Express's MyDHL API (v3.4.1), as far as Hirfaty uses it. Like
 * CathedisClient, nothing here touches our own rows — DhlShipmentService
 * decides what the answers mean.
 *
 * Basic auth on every call. Errors come back as `application/problem+json`:
 * `detail` says what went wrong, `additionalDetails` lists each refused field.
 */
class DhlExpressClient
{
    /** DHL's own limit on tracking numbers per tracking call. */
    public const TRACKING_BATCH = 200;

    public function baseUrl(): string
    {
        return rtrim((string) config('services.dhl_express.url'), '/');
    }

    /** Shipping is offered only once the login and the account are set. */
    public function isConfigured(): bool
    {
        return filled(config('services.dhl_express.username'))
            && filled(config('services.dhl_express.password'))
            && filled(config('services.dhl_express.account_number'));
    }

    /** Whether this is DHL's test environment, where nothing ships for real. */
    public function isTest(): bool
    {
        return str_contains($this->baseUrl(), '/test') || str_contains($this->baseUrl(), 'mock');
    }

    public function accountNumber(): string
    {
        return (string) config('services.dhl_express.account_number');
    }

    /**
     * Create a shipment: its waybill and piece numbers, the label and
     * commercial invoice as base64, and — when asked — the courier pickup.
     * With `$validateOnly`, DHL checks every field and creates nothing.
     *
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     *
     * @throws DhlExpressException
     */
    public function createShipment(array $shipment, bool $validateOnly = false): array
    {
        return $this->json(fn (PendingRequest $http) => $http->post(
            '/shipments'.($validateOnly ? '?validateDataOnly=true' : ''),
            $shipment,
        ));
    }

    /**
     * DHL's products and prices for a 1 kg, 20×15×10 cm box between two
     * places, shipped tomorrow — what `dhl:check` uses to prove the account
     * number works.
     *
     * @param  array<string, string|null>  $route  origin/destination country, city and postcode.
     * @return list<array<string, mixed>> DHL's `products`.
     *
     * @throws DhlExpressException
     */
    public function rates(array $route): array
    {
        $body = $this->json(fn (PendingRequest $http) => $http->get('/rates', array_filter([
            ...$route,
            'accountNumber' => $this->accountNumber(),
            'weight' => 1,
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'plannedShippingDate' => now('Africa/Casablanca')->addWeekday()->toDateString(),
            // DHL refuses a day it cannot collect on; this lets it take the next one.
            'nextBusinessDay' => 'true',
            'isCustomsDeclarable' => 'true',
            'unitOfMeasurement' => 'metric',
        ], fn ($value) => filled($value))));

        return array_values((array) ($body['products'] ?? []));
    }

    /**
     * DHL's price for one product and a basket's boxes, from the desk to a
     * destination: the account's own rates, fuel and surcharges included.
     * The town does not change the price (only the country, the weight and,
     * for remote areas, the postcode), but DHL requires one.
     *
     * @param  list<array{weight: float, dimensions?: array{length: float, width: float, height: float}}>  $packages
     * @return array<string, mixed>|null DHL's entry for that product, null when it does not offer it there.
     *
     * @throws DhlExpressException
     */
    public function rate(string $productCode, string $country, ?string $postalCode, ?string $city, array $packages): ?array
    {
        $shipper = (array) config('services.dhl_express.shipper');

        $body = $this->json(fn (PendingRequest $http) => $http->post('/rates', [
            'customerDetails' => [
                'shipperDetails' => [
                    'postalCode' => (string) ($shipper['postal_code'] ?? ''),
                    'cityName' => (string) ($shipper['city'] ?? ''),
                    'countryCode' => Str::upper((string) ($shipper['country'] ?? 'MA')),
                ],
                'receiverDetails' => [
                    'postalCode' => Str::limit((string) $postalCode, 12, ''),
                    'cityName' => Str::limit(filled($city) ? (string) $city : '-', 45, ''),
                    'countryCode' => Str::upper($country),
                ],
            ],
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->accountNumber()]],
            'productCode' => $productCode,
            'plannedShippingDateAndTime' => now('Africa/Casablanca')->addWeekday()->setTime(10, 0)->format('Y-m-d\TH:i:s \G\M\TP'),
            // DHL refuses a day it cannot collect on; this lets it take the next one.
            'nextBusinessDay' => true,
            'unitOfMeasurement' => 'metric',
            'isCustomsDeclarable' => true,
            'packages' => $packages,
        ]));

        $product = collect((array) ($body['products'] ?? []))->firstWhere('productCode', $productCode);

        return is_array($product) ? $product : null;
    }

    /**
     * Every checkpoint of up to 200 shipments. A waybill DHL does not know is
     * left out of the answer rather than failing the batch.
     *
     * @param  list<string>  $trackingNumbers
     * @return list<array<string, mixed>> DHL's `shipments`.
     *
     * @throws DhlExpressException
     */
    public function track(array $trackingNumbers): array
    {
        if ($trackingNumbers === []) {
            return [];
        }

        // DHL wants the parameter repeated (`?shipmentTrackingNumber=1&shipmentTrackingNumber=2`),
        // not PHP's `shipmentTrackingNumber[0]=…`.
        $query = collect(array_slice(array_values($trackingNumbers), 0, self::TRACKING_BATCH))
            ->map(fn (string $number) => 'shipmentTrackingNumber='.rawurlencode($number))
            ->push('trackingView=all-checkpoints', 'levelOfDetail=shipment')
            ->implode('&');

        $response = $this->send(fn (PendingRequest $http) => $http->get('/tracking?'.$query));

        if ($response->status() === 404) {
            return [];
        }

        return array_values((array) ($this->read($response)['shipments'] ?? []));
    }

    /**
     * Book a courier for a shipment already created without one.
     *
     * @param  array<string, mixed>  $pickup
     * @return list<string> DHL's dispatch confirmation numbers.
     *
     * @throws DhlExpressException
     */
    public function requestPickup(array $pickup): array
    {
        $body = $this->json(fn (PendingRequest $http) => $http->post('/pickups', $pickup));

        return array_values(array_filter((array) ($body['dispatchConfirmationNumbers'] ?? []), 'is_string'));
    }

    /**
     * Cancel a courier booking. DHL answers 200 whether it cancelled it now,
     * earlier, or the pickup already happened — `message` says which.
     *
     * @throws DhlExpressException
     */
    public function cancelPickup(string $confirmationNumber, string $requestor, string $reason): string
    {
        $body = $this->json(fn (PendingRequest $http) => $http->delete(
            '/pickups/'.rawurlencode($confirmationNumber).'?'.http_build_query([
                'requestorName' => Str::limit($requestor, 35, ''),
                'reason' => Str::limit($reason, 80, ''),
            ]),
        ));

        return (string) ($body['message'] ?? '');
    }

    /**
     * Whether DHL delivers at a postcode, and the town name it files it
     * under — what the desk's dialog suggests for a foreign address typed as
     * one line.
     *
     * @return list<array{cityName?: string, postalCode?: string, countryCode?: string}>
     *
     * @throws DhlExpressException
     */
    public function validateAddress(string $country, ?string $postalCode, ?string $city = null): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/address-validate', array_filter([
            'type' => 'delivery',
            'countryCode' => $country,
            'postalCode' => $postalCode,
            'cityName' => $city,
        ], fn ($value) => filled($value))));

        if ($response->status() === 404) {
            return [];
        }

        return array_values((array) ($this->read($response)['address'] ?? []));
    }

    /**
     * The electronic proof of delivery, as DHL's documents (base64 PDF).
     *
     * @return list<array{typeCode?: string, encodingFormat?: string, content?: string}>
     *
     * @throws DhlExpressException
     */
    public function proofOfDelivery(string $trackingNumber): array
    {
        $body = $this->json(fn (PendingRequest $http) => $http->get(
            '/shipments/'.rawurlencode($trackingNumber).'/proof-of-delivery',
            ['shipperAccountNumber' => $this->accountNumber(), 'content' => 'epod-summary'],
        ));

        return array_values((array) ($body['documents'] ?? []));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws DhlExpressException
     */
    private function json(callable $call): array
    {
        return $this->read($this->send($call));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws DhlExpressException
     */
    private function send(callable $call): Response
    {
        if (! $this->isConfigured()) {
            throw new DhlExpressException('Identifiants DHL Express non configurés (DHL_EXPRESS_USERNAME / DHL_EXPRESS_PASSWORD / DHL_EXPRESS_ACCOUNT_NUMBER).');
        }

        try {
            return $call($this->http());
        } catch (ConnectionException $e) {
            throw new DhlExpressException('DHL Express injoignable : '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws DhlExpressException
     */
    private function read(Response $response): array
    {
        $body = (array) ($response->json() ?? []);

        if ($response->successful()) {
            return $body;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new DhlExpressException('DHL Express refuse les identifiants (HTTP '.$response->status().') : vérifiez l’utilisateur, le mot de passe et l’environnement (test ou production).', $response->status());
        }

        $details = array_values(array_filter((array) ($body['additionalDetails'] ?? []), 'is_string'));
        $message = trim((string) ($body['detail'] ?? $body['message'] ?? $body['title'] ?? ''));

        throw new DhlExpressException(
            Str::limit(strip_tags($message !== '' ? $message : 'HTTP '.$response->status()), 500),
            $response->status(),
            array_map(fn (string $d) => Str::limit(strip_tags($d), 300), $details),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth(
                (string) config('services.dhl_express.username'),
                (string) config('services.dhl_express.password'),
            )
            // Quoted to DHL support to find one call in their logs.
            ->withHeaders(['Message-Reference' => (string) Str::uuid()])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(45);
    }
}
