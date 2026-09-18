<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NewsletterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only: who is on the footer's mailing list.
 *
 * There is nothing for an admin to edit here — see BrochureLeadController for
 * the same reasoning applied to another lead source.
 */
class NewsletterSubscriberController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletter) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        return Inertia::render('admin/newsletter-subscribers/index', [
            'subscribers' => $this->newsletter->paginatedSubscribers($search),
            'filters' => $request->only(['search']),
        ]);
    }
}
