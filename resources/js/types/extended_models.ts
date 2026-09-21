import type { Category, Media, Product, Store } from './models';

/** Axis kinds, tuned for craft goods. Mirrors ProductVariantOption::TYPES. */
export type VariantOptionType =
    | 'color'
    | 'dimension'
    | 'material'
    | 'finish'
    | 'pattern'
    | 'other';

export type VariantOption = {
    type: VariantOptionType;
    /** The axis, e.g. "Couleur". */
    name: string;
    /** The chosen point on it, e.g. "Rouge". */
    value: string;
};

/** One selector row, collapsed server-side from all of a product's variants. */
export type VariantOptionGroup = {
    name: string;
    type: VariantOptionType;
    values: { value: string }[];
};

export type ProductVariantResource = {
    id: number;
    sku: string | null;
    stock: number;
    in_stock: boolean;
    /** Effective price for the current viewer's price list. */
    price: number | null;
    /** Struck-through "was" price, or null when nothing to strike. */
    original_price: number | null;
    /** "Rouge / M". */
    label: string;
    options: VariantOption[];
    /** Variant photography; empty when the seller uploaded none. */
    images: { id: number; url: string }[];
};

export type BuyerProductResource = Omit<
    Product,
    | 'created_at'
    | 'updated_at'
    | 'status'
    | 'rejection_reason'
    | 'reviewed_by'
    | 'reviewed_at'
    // Omitted, not merged: the Eloquent-shaped `ProductVariant` and the
    // storefront's `ProductVariantResource` are different shapes (the resource
    // resolves prices for the viewer and flattens media). Intersecting them
    // would produce a variant type satisfiable by neither.
    | 'variants'
> & {
    images: Media[];
    /**
     * Own images plus every variant's, deduplicated — present only when the
     * backend eager-loaded `variants.media` for this response. Falls back to
     * `images` where absent.
     */
    gallery_images?: Media[];
    videos: Media[];
    category: Category;
    /**
     * Whether anything is actually buyable. Prefer this over `stock > 0`: a
     * variant product's own `stock` column is always 0 because stock lives on
     * its variants.
     */
    in_stock: boolean;
    /** Detail page only — the listing does not load variants. */
    variants?: ProductVariantResource[];
    option_groups?: VariantOptionGroup[];
    store: Pick<Store, 'id' | 'store_name' | 'slug' | 'description'> & {
        city_name: string;
        /** Public "Vérifié par Hirfaty" mark — see BuyerProductResource. */
        is_verified: boolean;
        /** Shop avatar; null when none was uploaded. */
        logo: string | null;
    };
};

// export interface BuyerCheckout {
//     cart: Cart;
// }
