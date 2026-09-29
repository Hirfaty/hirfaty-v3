<?php

namespace App\Services;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The footer's mailing list.
 *
 * Thin, but it owns the one rule that matters and that a controller would
 * otherwise be tempted to skip: an address is normalised before it is matched
 * or stored, so the unique index on `email` actually does its job.
 */
class NewsletterService
{
    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
    ) {}

    /**
     * Put a mailbox on the list, or refresh the one already there.
     *
     * Idempotent on purpose. A visitor who signs up twice — the same person on
     * a second device, or someone who simply forgot — must not produce a second
     * row, and must not be shown an error either: from their side both attempts
     * mean the same thing and both succeeded. `updateOrCreate` also keeps the
     * stored locale current, so someone who first subscribed while reading the
     * site in French and now reads it in Arabic gets mailed in Arabic.
     */
    public function subscribe(string $email, ?string $locale = null): NewsletterSubscriber
    {
        return NewsletterSubscriber::updateOrCreate(
            ['email' => $this->normalise($email)],
            ['locale' => $locale],
        );
    }

    /**
     * Whether this mailbox is already on the list.
     *
     * Normalises first, so it answers about the mailbox rather than about the
     * exact spelling it was typed in.
     */
    public function isSubscribed(string $email): bool
    {
        return NewsletterSubscriber::where('email', $this->normalise($email))->exists();
    }

    /**
     * The admin list of everyone on the mailing list, newest first.
     *
     * `$search` matches on the address only — it is the one field a subscriber
     * has, there is no name to search against.
     */
    public function paginatedSubscribers(?string $search = null): LengthAwarePaginator
    {
        return $this->adminQuery($search)
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * The admin list, unordered — shared by the table and the export.
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function adminQuery(?string $search = null): Builder
    {
        return NewsletterSubscriber::query()
            ->when($search, fn ($query) => $query->where('email', 'like', "%{$search}%"));
    }

    /**
     * Every subscriber the search matches, newest first, as CSV or Excel.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, ?string $search = null): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'newsletter',
            ['ID', 'Email', 'Langue', 'Inscrit le'],
            fn () => $this->adminQuery($search)->lazyByIdDesc(500)->map(fn (NewsletterSubscriber $subscriber) => [
                $subscriber->id,
                $subscriber->email,
                $subscriber->locale ?? '',
                $subscriber->created_at?->format('Y-m-d H:i') ?? '',
            ]),
        );
    }

    /**
     * Trimmed and lower-cased.
     *
     * `mb_strtolower` rather than `strtolower` because the local part of an
     * address is allowed to be non-ASCII, and the byte-wise version would leave
     * those characters untouched while folding the rest — producing two
     * spellings of one mailbox that the unique index cannot see are the same.
     */
    private function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
