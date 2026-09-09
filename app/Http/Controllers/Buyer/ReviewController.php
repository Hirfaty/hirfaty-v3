<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    /**
     * Record a buyer's ratings for one delivered order.
     *
     * One request for the whole order — every piece in it plus the shop —
     * rather than a request per star click: the form is a single "how did this
     * go?" moment, and submitting it should either land or not.
     *
     * Note what this method deliberately does *not* do: check that the order
     * belongs to the buyer, or that it was delivered. `ReviewService::submit`
     * owns both, so the rule cannot be satisfied by reaching the service some
     * other way. Validation here is about the shape of the payload only.
     */
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'products' => ['array'],
            // The keys are product ids. `integer` on the key pattern rather
            // than trusting the array shape: these are used to look rows up.
            'products.*.rating' => ['required', 'integer', 'between:1,5'],
            'products.*.comment' => ['nullable', 'string', 'max:1000'],

            'store' => ['nullable', 'array'],
            'store.rating' => ['required_with:store', 'integer', 'between:1,5'],
            'store.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviews->submit(
            $request->user(),
            $order,
            $data['products'] ?? [],
            $data['store'] ?? null,
        );

        return back()->with('success', __('buyer.reviews.thanks'));
    }
}
