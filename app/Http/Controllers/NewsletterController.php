<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscriptionRequest;
use App\Services\NewsletterService;
use Illuminate\Http\RedirectResponse;

/**
 * Signing up to the mailing list from the site footer.
 */
class NewsletterController extends Controller
{
    public function __construct(private NewsletterService $newsletter) {}

    public function store(NewsletterSubscriptionRequest $request): RedirectResponse
    {
        $this->newsletter->subscribe(
            $request->validated()['email'],
            app()->getLocale(),
        );

        /*
         * The same answer whether the address was new or already on the list.
         * Replying "you are already subscribed" would turn an open form into a
         * way of asking the site whether a given person has signed up — the
         * throttle on the route slows that down but does not make it private.
         */
        return back()->with('success', __('common.footer.newsletter.success'));
    }
}
