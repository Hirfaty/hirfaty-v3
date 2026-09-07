import type { Media, OrderStatus } from '@/types';

export interface Buyer {
    first_name: string;
    last_name: string;
    email: string | null;
    phone_number: string | null;
    created_at: string;
}

export interface AccountInfo {
    isProfessional: boolean;
    isVerified: boolean;
    requestStatus: 'pending' | 'approved' | 'declined' | null;
    requestMessage: string | null;
    requestedAt: string | null;
    declineReason: string | null;
    canRequest: boolean;
}

export interface AddressRow {
    id: number;
    label: string | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    address_line: string;
    /** Feeds the edit form's select; `city` is the resolved name, for display. */
    city_id: number | null;
    city: string | null;
    is_default: boolean;
}

/** Flattened by BuyerAccountService::orders() — not the raw Order model. */
export interface AccountOrder {
    id: number;
    reference: string;
    status: OrderStatus;
    total_amount: string;
    tracking_number: string | null;
    created_at: string;
    delivered_at: string | null;
    shipping_address: string;
    city: string | null;
    store: { store_name: string | null };
    items: {
        id: number;
        quantity: number;
        unit_price: string;
        subtotal: string;
        product: {
            name: string | null;
            slug: string | null;
            image: string | null;
        };
    }[];
}

export interface WishlistItem {
    id: number;
    name: string;
    slug: string;
    base_price: string | null;
    sale_price: string | null;
    in_stock: boolean;
    images: Media[];
    store: { store_name: string | null; city_name: string | null };
}
