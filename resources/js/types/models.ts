import type { User } from './auth';

/** How the seller behind a shop is legally organised. */
export type StoreType = 'artist' | 'company' | 'cooperative';

export interface Store {
    id: number;
    user_id: number;
    category_id: number | null;
    city_id: number | null;
    type: StoreType;
    city: City;
    user: User;
    store_name: string;
    slug: string;
    description: string | null;
    /** Public URL of the shop's square photo. */
    logo: string | null;
    /** Public URL of the shop's cover image. */
    banner: string | null;
    created_at: string;
    updated_at: string;
}

/** Locales a category name is stored in. */
export type CategoryLocale = 'fr' | 'ar' | 'en';

export interface Category {
    id: number;
    /** Per-locale names, e.g. { fr: 'Poterie', ar: '...', en: 'Pottery' }. */
    name: Record<CategoryLocale, string>;
    /** Name resolved for the active locale; appended server-side. */
    translated_name: string;
    slug: string;
    /**
     * The main categories this one is filed under — several are allowed, and
     * an empty list means it is itself a root. Only sent where the tree is
     * edited (the admin page); pickers that just list roots omit it.
     */
    parent_ids?: number[];
    /** Customs tariff code its products inherit; admin screens only. */
    hs_code?: string | null;
    /** Public URL of the picture; only top-level categories have one. */
    image_url: string | null;
    /**
     * Subcategories, when eager-loaded on a top-level category. The same
     * subcategory can appear under more than one parent.
     */
    children?: Category[];
}

export interface City {
    id: number;
    name: string;
    region: string | null;
}

/** Where an article sits in its lifecycle. Mirrors Blog::STATUSES. */
export type BlogStatus = 'draft' | 'published' | 'archived';

/**
 * An editorial article, as shaped by App\Http\Resources\BlogResource — not the
 * raw model.
 */
export interface Blog {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    /**
     * Sanitised HTML from the rich text editor, rendered with
     * dangerouslySetInnerHTML. Only sent by the screens that display an
     * article — the listings omit it, hence optional.
     */
    content?: string | null;
    status: BlogStatus;
    /** False for a draft or an archived post. */
    is_published: boolean;
    published_at: string | null;
    cover_url: string | null;
    reading_minutes: number;
    author?: { id: number; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface Media {
    id: number;
    mediable_id: number;
    mediable_type: string;
    type: 'image' | 'video';
    path: string;
    url: string;
    disk: string;
    mime_type: string | null;
    size: number | null;
    order: number;
    created_at: string;
    updated_at: string;
}

export type ProductStatus =
    | 'draft'
    | 'published'
    | 'archived'
    | 'rejected'
    | 'pending_review';

/** Which audience a product is sold to. */
export type ProductVisibility = 'individual' | 'professional' | 'both';

/** How a variant option is captured in the admin UI. */
export type VariantOptionType =
    | 'color'
    | 'dimension'
    | 'material'
    | 'finish'
    | 'pattern'
    | 'other';

/**
 * One axis/value pair on a variant, e.g. { name: 'Couleur', value: 'Rouge' }.
 * `name` is the axis, `value` the point on it — never the same string.
 */
export interface ProductVariantOption {
    id: number;
    product_variant_id: number;
    type: VariantOptionType;
    /** The axis, e.g. "Couleur". Canonical for all types but 'other'. */
    name: string;
    /** The chosen point, e.g. "Rouge". */
    value: string;
}

export interface ProductVariant {
    id: number;
    product_id: number;
    sku: string | null;
    stock: number;
    /** Null means "inherit the product's retail price". */
    price_override: string | null;
    /** Null means "inherit the product's professional price". */
    professional_price_override: string | null;
    /** Shipping overrides, in the product's units; null inherits the product's. */
    weight?: string | null;
    length?: string | null;
    width?: string | null;
    height?: string | null;
    position: number;
    is_active: boolean;
    options: ProductVariantOption[];
    media?: Media[];
}

export type WeightUnit = 'g' | 'kg';
export type DimensionUnit = 'cm' | 'm';

export interface Product {
    id: number;
    store_id: number;
    category_id: number | null;
    name: string;
    slug: string;
    sku: string | null;
    description: string | null;
    /**
     * Null when the admin catalogued the piece before agreeing a price. Such
     * a product never reaches the storefront, so buyer-facing screens can
     * treat it as always set; the admin screens must render the empty case.
     */
    base_price: string | null;
    sale_price: string | null;
    /** Professional (bulk) price list; null when sold at retail only. */
    professional_price: string | null;
    professional_sale_price: string | null;
    stock: number;
    min_order_qty_professional: number;
    visibility: ProductVisibility;
    has_variants: boolean;
    /** Shipping profile (see ProductShippingService), in the units beside it. */
    weight?: string | null;
    weight_unit?: WeightUnit;
    length?: string | null;
    width?: string | null;
    height?: string | null;
    dimension_unit?: DimensionUnit;
    /** ISO country the parcel leaves from — always MA for now. */
    origin_country?: string;
    /** Own tariff code; null inherits the category's. */
    hs_code?: string | null;
    customs_description?: string | null;
    is_fragile?: boolean;
    /** Days to prepare the parcel before pickup. */
    handling_days?: number | null;
    status: ProductStatus;
    average_rating: string;
    created_at: string;
    updated_at: string;
    rejection_reason: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    store: Store;
    images: Media[];
    videos: Media[];
    media?: Media[];
    variants?: ProductVariant[];
    category: Category | null;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    quantity: number;
    unit_price: string;
    subtotal: string;
    product: Product;
    order?: Order;
}

export type OrderStatus =
    | 'pending'
    | 'confirmed'
    | 'shipped'
    | 'delivered'
    | 'cancelled';

export interface Order {
    id: number;
    /**
     * Public-facing identifier, e.g. "HRF-2K8F-4M9Q". Display this and use it
     * in URLs — `id` is a global sequence and leaks total order volume.
     */
    reference: string;
    buyer_id: number;
    store_id: number;
    city_id: number | null;
    tracking_number: string | null;
    status: OrderStatus;
    total_amount: string;
    delivery_fee: string;
    /*
     * `platform_fee` and `seller_earnings` are absent on purpose: the model
     * hides them (app/Models/Order.php), so they are not in the payload at
     * all. Omitting them here makes rendering the platform's cut a type error
     * rather than a silent `undefined`.
     */
    shipping_address: string;
    notes: string | null;
    /** Why the order was cancelled, when it was — set by OrderStatusService. */
    cancellation_reason: string | null;
    delivered_at: string | null;
    buyer: User;
    items: OrderItem[];
    city: City | null;
    store?: Store;
    created_at: string;
    updated_at: string;
}

/*
 * The `Earning` interface that stood here is gone along with the screens that
 * rendered it. The ledger itself is untouched — CheckoutService still writes
 * an Earning per store on every checkout — but nothing in the UI reads what
 * Hirfaty or an artisan makes on an order, so no page needs the shape. Bring
 * it back (mirroring app/Models/Earning.php) if those figures are shown again.
 */

export interface CartItem {
    cart_id: number;
    created_at: string;
    id: number;
    product_id: string;
    product: Product;
    quantity: number;
    updated_at: string;
}

export interface Cart {
    id: number;
    user_id: number;
    created_at: string;
    updated_at: string;
    items: CartItem[];
}
