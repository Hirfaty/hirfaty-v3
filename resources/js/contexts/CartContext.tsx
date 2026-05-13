import React, {
    createContext,
    useContext,
    useState,
    useEffect,
    ReactNode,
} from 'react';

const CART_KEY = 'hirfaty_guest_cart';

export type GuestCartItem = {
    product_id: number;
    quantity: number;
    name: string;
    price: string;
    image: string | null;
    store_name: string;
};

type CartContextType = {
    items: GuestCartItem[];
    addItem: (item: GuestCartItem) => void;
    removeItem: (productId: number) => void;
    updateQuantity: (productId: number, quantity: number) => void;
    clearCart: () => void;
    count: number;
};

const CartContext = createContext<CartContextType | undefined>(undefined);

export function CartProvider({ children }: { children: ReactNode }) {
    const [items, setItems] = useState<GuestCartItem[]>(() => {
        try {
            const raw = localStorage.getItem(CART_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch {
            return [];
        }
    });

    useEffect(() => {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
    }, [items]);

    const addItem = (item: GuestCartItem) => {
        setItems((prev) => {
            const existing = prev.find((i) => i.product_id === item.product_id);
            if (existing) {
                return prev.map((i) =>
                    i.product_id === item.product_id
                        ? { ...i, quantity: i.quantity + item.quantity }
                        : i,
                );
            } else {
                return [...prev, item];
            }
        });
    };

    const removeItem = (productId: number) => {
        setItems((prev) => prev.filter((i) => i.product_id !== productId));
    };

    const updateQuantity = (productId: number, quantity: number) => {
        setItems((prev) =>
            prev.map((i) =>
                i.product_id === productId ? { ...i, quantity } : i,
            ),
        );
    };

    const clearCart = () => {
        setItems([]);
    };

    const count = items.reduce((sum, i) => sum + i.quantity, 0);

    return (
        <CartContext.Provider
            value={{
                items,
                addItem,
                removeItem,
                updateQuantity,
                clearCart,
                count,
            }}
        >
            {children}
        </CartContext.Provider>
    );
}

export function useGuestCart() {
    const context = useContext(CartContext);
    if (!context) {
        throw new Error('useGuestCart must be used within a CartProvider');
    }
    return context;
}
