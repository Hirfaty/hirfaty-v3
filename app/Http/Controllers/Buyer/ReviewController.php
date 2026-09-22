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
     * One request for the whole order rather than a request per star click:
     * the form is a single "how did this go?" moment, ratings are final once
     * sent, and submitting should either land or not.
     *
     * Note what this method deliberately does *not* do: check that the order
     * belongs to the buyer, that it was delivered, or that the pieces are
     * still unrated. `ReviewService::submit` owns all three, so the rules
     * cannot be satisfied by reaching the service some other way. Validation
     * here is about the shape of the payload only.
     */
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'products' => ['array'],
            // The keys are product ids. `integer` on the key pattern rather
            // than trusting the array shape: these are used to look rows up.
            'products.*.rating' => ['required', 'integer', 'between:1,5'],
            'products.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviews->submit(
            $request->user(),
            $order,
            $data['products'] ?? [],
        );

        return back()->with('success', __('buyer.reviews.thanks'));
    }
}
