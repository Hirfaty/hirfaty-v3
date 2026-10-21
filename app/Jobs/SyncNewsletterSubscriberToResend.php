<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\ResendContactService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies one newsletter signup into the Resend "Newsletter" segment.
 *
 * Never throws. A failure is logged and, while attempts remain, the job is put
 * back on the queue a little later; after the last one the subscriber simply
 * stays on our own list without the Resend copy. Swallowing rather than
 * throwing matters on the `sync` driver, where a thrown exception would come
 * back out of the signup request itself.
 */
class SyncNewsletterSubscriberToResend implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Unsubscribed (row deleted) before the job ran: nothing left to copy. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function handle(ResendContactService $resend): void
    {
        if (! $resend->isConfigured()) {
            return;
        }

        try {
            $resend->addToNewsletterSegment($this->subscriber->email);
        } catch (Throwable $e) {
            Log::warning("Resend newsletter sync failed for subscriber {$this->subscriber->id} (attempt {$this->attempts()}): ".$e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());
            }
        }
    }
}
