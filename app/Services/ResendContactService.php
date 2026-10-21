<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Hirfaty's mailing list, mirrored into Resend.
 *
 * Every footer signup is copied into one Resend segment ("Newsletter",
 * `services.resend.newsletter_segment_id`) so broadcasts can be sent from
 * Resend's dashboard. Our `newsletter_subscribers` table stays the source of
 * truth; this is a copy, made from a queued job
 * (`SyncNewsletterSubscriberToResend`) so Resend being slow or down never
 * touches the signup itself.
 *
 * Talks to the REST API through Laravel's HTTP client rather than the
 * resend-php SDK, which brings its own Guzzle and so cannot be faked in tests.
 */
class ResendContactService
{
    private const BASE_URL = 'https://api.resend.com';

    /** Both the key and the segment are needed; with either blank, nothing is sent. */
    public function isConfigured(): bool
    {
        return filled(config('services.resend.key'))
            && filled(config('services.resend.newsletter_segment_id'));
    }

    /**
     * Make sure this mailbox is a subscribed contact in the newsletter segment.
     *
     * Idempotent, like the signup it mirrors. A new address is created straight
     * into the segment in one call. An address Resend already knows — someone
     * who signed up twice, or whom Resend got some other way — is instead
     * marked subscribed again (they just asked to be) and added to the segment;
     * Resend saying it is already there counts as success.
     *
     * @throws RequestException|ConnectionException when Resend refuses or cannot be reached
     */
    public function addToNewsletterSegment(string $email): void
    {
        $segmentId = (string) config('services.resend.newsletter_segment_id');

        $created = $this->client()->post('/contacts', [
            'email' => $email,
            'unsubscribed' => false,
            'segments' => [['id' => $segmentId]],
        ]);

        if ($created->successful()) {
            return;
        }

        /*
         * Resend does not document how it answers a duplicate, so any client
         * error that is not about our credentials or our rate is read as
         * "this contact exists" and handled by the path below. If it was
         * something else (a malformed address, say), the update there fails
         * too and that error is the one reported.
         */
        if (! $created->clientError() || in_array($created->status(), [401, 403, 429], true)) {
            $created->throw();
        }

        $contact = '/contacts/'.rawurlencode($email);

        $this->client()->patch($contact, ['unsubscribed' => false])->throw();

        $added = $this->client()->post($contact.'/segments/'.rawurlencode($segmentId));

        if ($added->failed() && ! $this->isAlreadyMember($added)) {
            $added->throw();
        }
    }

    private function isAlreadyMember(Response $response): bool
    {
        return $response->status() === 409
            || str_contains(strtolower((string) $response->json('message')), 'already');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken((string) config('services.resend.key'))
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }
}
