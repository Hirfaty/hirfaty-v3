<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Which price list a visitor shops on: "particulier" or "professionnel".
 *
 * Anyone may pick either — there is no trade account to apply for and nothing
 * for an admin to approve. Every visitor chooses on their first visit (the
 * chooser popup) and can flip it from the header at any time. The choice
 * decides, in one place, both what the storefront quotes and what checkout
 * bills:
 *
 *   - the price on every card, product page and basket line;
 *   - which pieces are listed (a trade-only piece in pro mode only, a
 *     consumer-only one in individual mode only);
 *   - the quantity floor: on the pro list a piece must be bought by at least
 *     its `min_order_qty_professional` (Product::minOrderQtyFor), set per
 *     product by the admin desk. Retail is from one piece.
 *
 * Kept in a long-lived cookie rather than the session: the chooser is a
 * first-visit question, and a session that expires after two hours would ask
 * it again on the next visit. Every request that prices anything — the
 * basket, Buy now, checkout — is the buyer's own browser, so the cookie is
 * always there to read; nothing is billed from a webhook (the orders carry
 * their prices from the moment CheckoutService writes them).
 */
class PriceModeService
{
    public const COOKIE = 'hirfaty_price_mode';

    /** One year — this is a standing preference, like a language. */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    /** Request attribute the resolved mode is memoised under. */
    private const ATTRIBUTE = 'hirfaty.price_mode';

    public const MODES = [
        Product::AUDIENCE_INDIVIDUAL,
        Product::AUDIENCE_PROFESSIONAL,
    ];

    public function __construct(private CurrencyService $currency) {}

    /**
     * The price list for this request — shown and billed alike.
     *
     * A crawler always reads retail: Merchant Center disapproves a product
     * whose landing page disagrees with the feed, which carries retail.
     * Everyone else gets their own choice, retail until they make one.
     *
     * Memoised on the request: the product resources ask once per card.
     */
    public function audience(Request $request): string
    {
        $cached = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($cached)) {
            return $cached;
        }

        $mode = $this->currency->isCrawler($request)
            ? Product::AUDIENCE_INDIVIDUAL
            : ($this->chosen($request) ?? Product::AUDIENCE_INDIVIDUAL);

        $request->attributes->set(self::ATTRIBUTE, $mode);

        return $mode;
    }

    /**
     * Whether this visitor still has to be asked.
     *
     * Never asked: a crawler, and the back-office roles — a seller or an
     * admin is not shopping.
     */
    public function needsChoice(Request $request): bool
    {
        $user = $request->user();

        if ($this->currency->isCrawler($request)) {
            return false;
        }

        if ($user !== null && ! $user->isBuyer()) {
            return false;
        }

        return $this->chosen($request) === null;
    }

    /**
     * Store a choice. False for anything that is not a mode, so the caller can
     * 404 rather than write junk into the cookie.
     */
    public function remember(string $mode): bool
    {
        if (! in_array($mode, self::MODES, true)) {
            return false;
        }

        Cookie::queue(self::COOKIE, $mode, self::COOKIE_MINUTES);

        return true;
    }

    /**
     * Shared on every response (HandleInertiaRequests) for the header switch,
     * the chooser and the pages' minimum-quantity hints.
     *
     * @return array{mode: string, needs_choice: bool}
     */
    public function payload(Request $request): array
    {
        return [
            'mode' => $this->audience($request),
            'needs_choice' => $this->needsChoice($request),
        ];
    }

    /** Search and shopping bots, which always read retail. */
    public function isCrawler(Request $request): bool
    {
        return $this->currency->isCrawler($request);
    }

    /** The cookie's mode, or null when absent or not a mode. */
    private function chosen(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) && in_array($value, self::MODES, true) ? $value : null;
    }
}
