import { usePage } from '@inertiajs/react';

/**
 * One cart line, in the single shape the header badge and the cart drawer both
 * read — whichever side of logging in the shopper is on.
 */
export type CartSummaryLine = {
    /** Cart-item id. Key only. */
    id: number;
    name: string;
    /** "Rouge / 4m" — absent on a product without variants. */
    variant?: string | null;
    image?: string | null;
    seller: string;
    qty: number;
    price: number;
    line_total: number;
};

export type CartSummary = {
    items: CartSummaryLine[];
    count: number;
    subtotal: number;
};

type CartPageProps = {
    auth: { user: { id: number } | null };
    cart?: CartSummary;
};

/**
 * The basket as the chrome should show it — always the server's, shared by
 * `HandleInertiaRequests`: the account's basket when signed in, the one kept
 * against the visitor's cookie when not (CartService::current). Both go
 * through the same pricing, so the header never quotes a total checkout
 * disagrees with.
 *
 * Lives in a hook rather than in `SiteHeader` because the drawer needs the same
 * figures and can be opened from a product page.
 */
export function useCartSummary(): CartSummary {
    const { cart } = usePage<CartPageProps>().props;

    return cart ?? { items: [], count: 0, subtotal: 0 };
}
