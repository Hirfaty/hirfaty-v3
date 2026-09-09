/**
 * The buyer's order history, as `BuyerAccountService::orders()` shapes it.
 *
 * Declared here rather than inside the orders page because the review panel
 * and the page both read it, and a second hand-written copy of a shape this
 * wide is how the two quietly disagree.
 */

export type OrderStatus =
    | 'pending'
    | 'confirmed'
    | 'shipped'
    | 'delivered'
    | 'cancelled';

export type BuyerOrderReview = {
    rating: number;
    comment: string | null;
};

export type BuyerOrderItem = {
    id: number;
    /**
     * Null once the product itself has been deleted — the order item keeps its
     * snapshot, but there is nothing left to rate, so the review panel skips
     * these rather than posting a null key.
     */
    product_id: number | null;
    quantity: number;
    unit_price: number | string;
    subtotal: number | string;
    product: {
        name: string | null;
        slug: string | null;
        image: string | null;
    };
    /** This buyer's existing rating of the piece, if they left one. */
    review: BuyerOrderReview | null;
};

export type BuyerOrder = {
    id: number;
    reference: string;
    status: OrderStatus;
    total_amount: number | string;
    tracking_number: string | null;
    created_at: string | null;
    delivered_at: string | null;
    shipping_address: string | null;
    city: string | null;
    store: {
        store_name: string | null;
    };
    /**
     * Whether the rating form is offered. Server-decided (delivered orders
     * only) — the panel never infers it from `status` itself, so the rule
     * lives in one place.
     */
    can_review: boolean;
    /** This buyer's existing rating of the shop for this delivery. */
    store_review: BuyerOrderReview | null;
    items: BuyerOrderItem[];
};
