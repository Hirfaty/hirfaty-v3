<?php

use App\Services\SmsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The SMS drivers: Messagepit locally (the inbox that catches local mail and
 * texts alike), Twilio in production. Both speak the same Twilio request, which is
 * the point of using Messagepit — locally exercises what production sends.
 */
afterEach(function () {
    app()['env'] = 'testing';
});

test('messagepit receives the same request Twilio would', function () {
    config([
        'services.sms.driver' => 'messagepit',
        'services.sms.messagepit' => ['url' => 'http://127.0.0.1:8200/', 'token' => null, 'from' => 'Hirfaty'],
    ]);

    Http::fake(['127.0.0.1:8200/*' => Http::response(['sid' => 'TN1', 'status' => 'queued'], 201)]);

    app(SmsService::class)->send('+212612345678', 'Hirfaty: your code is 123456.');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:8200/2010-04-01/Accounts/AC00000000000000000000000000000000/Messages.json'
        && $request->method() === 'POST'
        && $request['To'] === '+212612345678'
        && $request['From'] === 'Hirfaty'
        && $request['Body'] === 'Hirfaty: your code is 123456.'
        && $request->hasHeader('Authorization'));
});

test('a messagepit that is not running says how to start it', function () {
    config(['services.sms.driver' => 'messagepit']);

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => app(SmsService::class)->send('+212612345678', 'hello'))
        ->toThrow(RuntimeException::class, 'docker compose up -d messagepit');
});

test('twilio is sent to Twilio with the account credentials', function () {
    config([
        'services.sms.driver' => 'twilio',
        'services.sms.twilio' => ['sid' => 'ACreal', 'token' => 'secret', 'from' => 'Hirfaty'],
    ]);

    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    app(SmsService::class)->send('+33612345678', 'hello');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/ACreal/Messages.json'
        && $request['To'] === '+33612345678');
});

test('a refused text is an error the sign-in screens can show', function () {
    config(['services.sms.driver' => 'messagepit']);

    Http::fake(['*' => Http::response(['message' => 'The To number is invalid'], 400)]);

    expect(fn () => app(SmsService::class)->send('+15005550001', 'hello'))
        ->toThrow(RuntimeException::class, 'The To number is invalid');
});

test('in production only Twilio counts as a way to reach a phone', function (string $driver, bool $available) {
    config(['services.sms.driver' => $driver]);
    app()['env'] = 'production';

    expect(app(SmsService::class)->available())->toBe($available);
})->with([
    'log' => ['log', false],
    'messagepit' => ['messagepit', false],
    'twilio' => ['twilio', true],
]);

test('locally every driver may be used', function (string $driver) {
    config(['services.sms.driver' => $driver]);

    expect(app(SmsService::class)->available())->toBeTrue();
})->with(['log', 'messagepit', 'twilio']);
