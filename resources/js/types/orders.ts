/**
 * The buyer's order history, as `BuyerAccountService::purchases()` shapes it.
 *
 * Two levels, mirroring the two things "order" means here: a `BuyerPurchase`
 * is one checkout as the buyer made it, and the `BuyerOrder`s inside it are
 * how that basket was split across workshops for fulfilment and payment.
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

/** One checkout, however many workshops it reached. */
export type BuyerPurchase = {
    /**
     * The group reference every order from this checkout shares — what the
     * buyer quotes for the purchase as a whole. A lone order placed before
     * grouping existed carries its own reference here instead.
     */
    reference: string;
    /**
     * Rolled up from the shops: the least advanced one sets the pace, and
     * `cancelled` only when every shop was. Server-decided — see
     * BuyerAccountService::statusFor().
     */
    status: OrderStatus;
    created_at: string | null;
    /** The pieces, before delivery. */
    items_total: number;
    /**
     * Charged once for the basket, not per shop — which is why it is here and
     * not on `BuyerOrder`, whose own `total_amount` is that shop's pieces only.
     */
    delivery_fee: number;
    /** `items_total` + `delivery_fee` — what the purchase actually cost. */
    total_amount: number;
    shipping_address: string | null;
    city: string | null;
    /** Whether any shop in this purchase has arrived and can be rated. */
    can_review: boolean;
    /** One per workshop, each with its own status, tracking and rating form. */
    orders: BuyerOrder[];
};
