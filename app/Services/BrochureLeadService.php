<?php

namespace App\Services;

use App\Models\BrochureLead;
use Illuminate\Support\Facades\Storage;

/**
 * The homepage brochure request: recording who asked, and handing back the file.
 *
 * Two rules live here that a controller would be tempted to skip. An address is
 * normalised before it is matched or stored, so the unique index on `email`
 * actually does its job; and `seller_type` is cleared for a buyer, so the column
 * never carries an answer to a question that was not asked.
 */
class BrochureLeadService
{
    /**
     * Where the PDF sits on the `local` disk.
     *
     * Not in `public/`: the whole point of the form is that the brochure is
     * given out in exchange for the details, and a file under the document root
     * has a guessable URL that skips it. It is served by
     * `BrochureController::download` instead, and is gitignored — see the
     * deployment note in that controller.
     */
    public const PATH = 'brochure/hirfaty-brochure.pdf';

    /**
     * The filename the visitor's browser saves it as.
     */
    public const DOWNLOAD_NAME = 'hirfaty-brochure.pdf';

    /**
     * Record a request, or fold it into the one already there.
     *
     * Idempotent on the mailbox. Someone who comes back for the brochure a
     * second time — a new device, a lost download, a colleague's forward — must
     * not produce a second row and must not be shown an error either: from
     * their side both attempts mean the same thing and both succeeded.
     * `updateOrCreate` refreshes the details too, so a lead who has since
     * changed name, side of the marketplace or reading language is stored as
     * they are now, while `downloads_count` keeps the fact that they returned.
     *
     * @param  array{first_name: string, last_name: string, email: string, type: string, seller_type?: string|null}  $data
     */
    public function record(array $data, ?string $locale = null): BrochureLead
    {
        $email = $this->normalise($data['email']);
        $existing = BrochureLead::where('email', $email)->first();

        return BrochureLead::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'type' => $data['type'],
                // A buyer is never asked which kind of seller they are, so any
                // value that arrives alongside `type = buyer` is stale state
                // from the dialog rather than an answer.
                'seller_type' => $data['type'] === 'seller'
                    ? ($data['seller_type'] ?? null)
                    : null,
                'locale' => $locale,
                'downloads_count' => ($existing?->downloads_count ?? 0) + 1,
            ],
        );
    }

    /**
     * Whether the brochure is actually on disk.
     *
     * Worth asking before promising a download: the PDF is gitignored, so a
     * freshly deployed environment where nobody uploaded it would otherwise
     * take the visitor's details and then hand them a 404.
     */
    public function exists(): bool
    {
        return Storage::disk('local')->exists(self::PATH);
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
