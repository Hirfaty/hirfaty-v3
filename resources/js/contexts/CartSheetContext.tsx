import type { ReactNode } from 'react';
import { createContext, useContext, useState } from 'react';

type CartSheetContextType = {
    open: boolean;
    setOpen: (open: boolean) => void;
    openCart: () => void;
    closeCart: () => void;
};

const CartSheetContext = createContext<CartSheetContextType | undefined>(
    undefined,
);

/**
 * Whether the cart drawer is showing.
 *
 * Global rather than local to `SiteHeader` because the thing that opens it is
 * usually somewhere else entirely — the Add-to-Cart button on a product page —
 * and the header is mounted as a sibling of it, not a parent. Sits above the
 * Inertia app in `app.tsx`, so it survives visits the same way the header does.
 */
export function CartSheetProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <CartSheetContext.Provider
            value={{
                open,
                setOpen,
                openCart: () => setOpen(true),
                closeCart: () => setOpen(false),
            }}
        >
            {children}
        </CartSheetContext.Provider>
    );
}

export function useCartSheet() {
    const context = useContext(CartSheetContext);

    if (!context) {
        throw new Error('useCartSheet must be used within a CartSheetProvider');
    }

    return context;
}
