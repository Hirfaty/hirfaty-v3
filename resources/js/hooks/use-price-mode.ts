import type { VisitOptions } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';

export type PriceMode = 'individual' | 'professional';

export type PriceModePayload = {
    /** The price list this visitor shops on — shown and billed alike. */
    mode: PriceMode;
    /** True until this visitor has picked a side in the chooser. */
    needs_choice: boolean;
};

/** What the server falls back to, mirrored for a response without the prop. */
const FALLBACK: PriceModePayload = {
    mode: 'individual',
    needs_choice: false,
};

/**
 * Change which price list the visitor shops on.
 *
 * Remounts the page by default (`preserveState: false`): the product page
 * opens its quantity on the price list's minimum, and that only resets on a
 * fresh mount. The chooser passes `preserveState: true` instead, so the
 * popup can close itself while the prices behind it change.
 */
export const switchPriceMode = (mode: PriceMode, options: VisitOptions = {}) =>
    router.post(
        route('price-mode.switch', mode),
        {},
        { preserveScroll: true, preserveState: false, ...options },
    );

/**
 * "Particulier" or "pro", shared on every response (HandleInertiaRequests →
 * PriceModeService). Anyone may pick either; on the pro list each piece is
 * sold from the minimum quantity the admin set on it.
 */
export function usePriceMode() {
    const props = usePage().props as unknown as {
        priceMode?: PriceModePayload;
    };

    const priceMode = props.priceMode ?? FALLBACK;

    return {
        ...priceMode,
        isPro: priceMode.mode === 'professional',
    };
}
