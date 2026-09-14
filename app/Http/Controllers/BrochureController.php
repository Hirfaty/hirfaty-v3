<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrochureLeadRequest;
use App\Services\BrochureLeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The homepage brochure: the form that unlocks it, and the file itself.
 *
 * Two routes rather than one because the request arrives over Inertia, which
 * expects a redirect back and cannot do anything with a PDF body. So `request`
 * records the lead and marks the session, and the dialog then sends the browser
 * to `download` — an ordinary GET that the browser can save.
 *
 * Deployment note: the PDF is not in the repository (35 MB, and it changes on
 * its own schedule). Put it at `storage/app/private/brochure/hirfaty-brochure.pdf`
 * — see `BrochureLeadService::PATH` — on each environment.
 */
class BrochureController extends Controller
{
    /**
     * Session key marking that this visitor has paid the toll.
     */
    private const UNLOCKED = 'brochure.unlocked';

    public function __construct(private BrochureLeadService $brochure) {}

    public function request(BrochureLeadRequest $request): RedirectResponse
    {
        /*
         * Checked before the row is written, not after: taking someone's
         * details and then handing them a 404 is worse than telling them the
         * brochure is briefly unavailable, and it would quietly fill the lead
         * table on an environment where the file was never uploaded.
         */
        if (! $this->brochure->exists()) {
            return back()->withErrors(
                ['brochure' => __('welcome.brochure.dialog.invalid')],
                'brochure',
            );
        }

        $this->brochure->record($request->validated(), app()->getLocale());

        $request->session()->put(self::UNLOCKED, true);

        return back()->with('success', __('welcome.brochure.dialog.success'));
    }

    public function download(Request $request): StreamedResponse
    {
        /*
         * The gate. Without it the download URL is a public, guessable link to
         * the file and the form above is decoration — anyone could pass it
         * around and nobody would ever be recorded.
         */
        abort_unless($request->session()->get(self::UNLOCKED), 403);

        abort_unless($this->brochure->exists(), 404);

        /*
         * Streamed rather than `->download()` with the file read into memory:
         * this is a 35 MB PDF, and buffering one copy per concurrent request
         * is how a modest download turns into an out-of-memory error.
         */
        return Storage::disk('local')->download(
            BrochureLeadService::PATH,
            BrochureLeadService::DOWNLOAD_NAME,
        );
    }
}
