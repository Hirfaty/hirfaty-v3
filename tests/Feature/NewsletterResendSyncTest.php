<?php

use App\Jobs\SyncNewsletterSubscriberToResend;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Footer signups copied into the Resend "Newsletter" segment.
 *
 * The queue is `sync` in tests, so the job runs inside the signup request —
 * which is exactly the setting where a Resend failure would leak into the
 * visitor's response if the job let it.
 */
beforeEach(function () {
    config([
        'services.resend.key' => 're_test_key',
        'services.resend.newsletter_segment_id' => 'seg-newsletter',
    ]);

    Http::preventStrayRequests();
});

it('queues the Resend sync when someone subscribes', function () {
    Queue::fake();

    $this->post('/newsletter', ['email' => '  Amina@Example.com '])->assertSessionHas('success');

    Queue::assertPushed(
        SyncNewsletterSubscriberToResend::class,
        fn ($job) => $job->subscriber->email === 'amina@example.com',
    );
});

it('creates the contact in the configured segment', function () {
    Http::fake([
        'api.resend.com/contacts' => Http::response(['object' => 'contact', 'id' => 'c-1'], 201),
    ]);

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.resend.com/contacts'
        && $request->hasHeader('Authorization', 'Bearer re_test_key')
        && $request['email'] === 'amina@example.com'
        && $request['unsubscribed'] === false
        && $request['segments'] === [['id' => 'seg-newsletter']]);
});

it('adds a contact Resend already has to the segment without failing', function () {
    Http::fake([
        'api.resend.com/contacts' => Http::response(['message' => 'Contact already exists.'], 409),
        'api.resend.com/contacts/amina%40example.com' => Http::response(['object' => 'contact', 'id' => 'c-1']),
        'api.resend.com/contacts/amina%40example.com/segments/seg-newsletter' => Http::response(['id' => 'seg-newsletter']),
    ]);
    Log::spy();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request['unsubscribed'] === false);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.resend.com/contacts/amina%40example.com/segments/seg-newsletter');
    Log::shouldNotHaveReceived('warning');
});

it('treats "already in the segment" as success', function () {
    Http::fake([
        'api.resend.com/contacts' => Http::response(['message' => 'Contact already exists.'], 422),
        'api.resend.com/contacts/amina%40example.com' => Http::response(['object' => 'contact', 'id' => 'c-1']),
        'api.resend.com/contacts/amina%40example.com/segments/seg-newsletter' => Http::response(['message' => 'Contact is already in this segment.'], 422),
    ]);
    Log::spy();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Log::shouldNotHaveReceived('warning');
});

it('makes no Resend call when the segment id is not set', function () {
    config(['services.resend.newsletter_segment_id' => '']);
    Queue::fake();
    Http::fake();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Queue::assertNotPushed(SyncNewsletterSubscriberToResend::class);
    Http::assertNothingSent();
    expect(NewsletterSubscriber::count())->toBe(1);
});

it('makes no Resend call when the API key is not set', function () {
    config(['services.resend.key' => null]);
    Http::fake();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Http::assertNothingSent();
});

it('skips a queued sync if the config was cleared before it ran', function () {
    Http::fake();
    $subscriber = NewsletterSubscriber::create(['email' => 'amina@example.com']);
    config(['services.resend.key' => '']);

    SyncNewsletterSubscriberToResend::dispatchSync($subscriber);

    Http::assertNothingSent();
});

it('still subscribes the visitor when Resend fails, and logs it', function () {
    Http::fake([
        'api.resend.com/*' => Http::response(['message' => 'Internal server error'], 500),
    ]);
    Log::spy();

    $response = $this->post('/newsletter', ['email' => 'amina@example.com']);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertSessionHasNoErrors();
    expect(NewsletterSubscriber::where('email', 'amina@example.com')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message) => str_contains($message, 'Resend newsletter sync failed'),
    );
});

it('still subscribes the visitor when Resend cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));
    Log::spy();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    expect(NewsletterSubscriber::count())->toBe(1);
    Log::shouldHaveReceived('warning')->once();
});

it('reports a rejected API key instead of retrying it as an existing contact', function () {
    Http::fake([
        'api.resend.com/contacts' => Http::response(['message' => 'API key is invalid'], 401),
    ]);
    Log::spy();

    $this->post('/newsletter', ['email' => 'amina@example.com'])->assertSessionHas('success');

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once();
});
