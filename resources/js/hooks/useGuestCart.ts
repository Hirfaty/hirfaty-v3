const CART_KEY = 'hirfaty_guest_cart';
const CART_EVENT = 'guest-cart-updated';

export type GuestCartItem = {
    product_id: number;
    quantity: number;
    name: string;
    price: string;
    image: string | null;
    store_name: string;
};

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
        const existing = items.find((i) => i.product_id === item.product_id);

        if (existing) {
            existing.quantity += item.quantity;
        } else {
            items.push(item);
        }

        saveItems(items);
    };

    const removeItem = (productId: number) => {
        saveItems(getItems().filter((i) => i.product_id !== productId));
    };

    const updateQuantity = (productId: number, quantity: number) => {
        const items = getItems();
        const item = items.find((i) => i.product_id === productId);

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
