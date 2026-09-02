const CART_KEY = 'hirfaty_guest_cart';
const CART_EVENT = 'guest-cart-updated';

export type GuestCartItem = {
    product_id: number;
    /**
     * Which variant was picked, or null for a product without variants.
     * Optional on read because carts saved before variants existed carry no
     * such key — a missing value means null, not a distinct line.
     */
    variant_id?: number | null;
    /** "Rouge / M" — so the header and cart can identify the line. */
    variant_label?: string | null;
    quantity: number;
    name: string;
    price: string;
    image: string | null;
    store_name: string;
};

/**
 * A cart line is identified by the product *and* the variant: "Rouge / M" and
 * "Bleu / L" are two lines of one product, and matching on product_id alone
 * would silently collapse the second into the first.
 */
const lineKey = (productId: number, variantId?: number | null) =>
    `${productId}:${variantId ?? ''}`;

const sameLine = (
    item: GuestCartItem,
    productId: number,
    variantId?: number | null,
) =>
    lineKey(item.product_id, item.variant_id) === lineKey(productId, variantId);

export function useGuestCart() {
    const getItems = (): GuestCartItem[] => {
        try {
            const raw = localStorage.getItem(CART_KEY);

            return raw ? JSON.parse(raw) : [];
        } catch {
            return [];
        }
    };

    const saveItems = (items: GuestCartItem[]) => {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
        window.dispatchEvent(new Event(CART_EVENT));
    };

    const addItem = (item: GuestCartItem) => {
        const items = getItems();
        const existing = items.find((i) =>
            sameLine(i, item.product_id, item.variant_id),
        );

        if (existing) {
            existing.quantity += item.quantity;
        } else {
            items.push({ ...item, variant_id: item.variant_id ?? null });
        }

        saveItems(items);
    };

    const removeItem = (productId: number, variantId?: number | null) => {
        saveItems(getItems().filter((i) => !sameLine(i, productId, variantId)));
    };

    const updateQuantity = (
        productId: number,
        quantity: number,
        variantId?: number | null,
    ) => {
        const items = getItems();
        const item = items.find((i) => sameLine(i, productId, variantId));

        if (item) {
            item.quantity = quantity;
            saveItems(items);
        }
    };

    const clearCart = () => {
        localStorage.removeItem(CART_KEY);
    };

    const count = () => getItems().reduce((sum, i) => sum + i.quantity, 0);

    return { getItems, addItem, removeItem, updateQuantity, clearCart, count };
}
