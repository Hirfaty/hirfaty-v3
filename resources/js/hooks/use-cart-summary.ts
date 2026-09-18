import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useGuestCart } from '@/hooks/useGuestCart';

/**
 * One cart line, in the single shape the header badge and the cart drawer both
 * read — whichever side of logging in the shopper is on.
 */
export type CartSummaryLine = {
    /** Cart-item id when signed in, product id for a guest line. Key only. */
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
 * The basket as the chrome should show it: the server cart shared by
 * `HandleInertiaRequests` for a signed-in shopper, the localStorage cart for a
 * guest, normalised to one shape.
 *
 * Lives in a hook rather than in `SiteHeader` because the drawer needs the same
 * figures and can be opened from a product page — two readers of one basket
 * must not each do their own arithmetic on it.
 */
export function useCartSummary(): CartSummary {
    const { cart, auth } = usePage<CartPageProps>().props;
    const { getItems, count: guestCount } = useGuestCart();

    const [guestItems, setGuestItems] = useState(getItems);

    /*
     * The guest cart is plain localStorage, which React cannot observe — every
     * write dispatches `guest-cart-updated` (see useGuestCart) and this is what
     * turns that into a re-render.
     */
    useEffect(() => {
        const sync = () => setGuestItems(getItems());

        window.addEventListener('guest-cart-updated', sync);

        return () => window.removeEventListener('guest-cart-updated', sync);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (auth?.user) {
        return cart ?? { items: [], count: 0, subtotal: 0 };
    }

    return {
        items: guestItems.map((item) => ({
            id: item.product_id,
            name: item.name,
            // Guest lines keep the label under a different key; surface it
            // under the same name the server cart uses so the drawer renders
            // identically either side of logging in.
            variant: item.variant_label ?? null,
            image: item.image,
            seller: item.store_name,
            qty: item.quantity,
            price: Number(item.price),
            line_total: Number(item.price) * item.quantity,
        })),
        count: guestCount(),
        subtotal: guestItems.reduce(
            (sum, item) => sum + Number(item.price) * item.quantity,
            0,
        ),
    };
}
