import { Link } from '@inertiajs/react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import type { Media } from '@/types';

/** Museum-caption label, shared with the pages this card sits on. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

/**
 * Shape of one row from BuyerProductResource.
 *
 * Declared here rather than reusing the shared `BuyerProductResource` type:
 * the professional block is conditionally merged server-side (only trade
 * accounts receive it), which an `Omit<Product, …>` cannot express.
 */
export type ListingProduct = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    /**
     * Null while an admin has catalogued the piece but not yet agreed its
     * price. Normally kept off every buyer-facing query by
     * `Product::scopeStorefront()` — currently relaxed there
     * (`storefront(false)`) as a temporary, demo-only carve-out, so this has
     * to be handled here rather than assumed present.
     */
    base_price: string | null;
    sale_price: string | null;
    stock: number;
    has_variants: boolean;
    average_rating: string | null;
    category: { id: number; translated_name: string } | null;
    store: {
        id: number | null;
        slug: string | null;
        store_name: string | null;
        city_name: string | null;
    };
    images: Media[];
    wishlists_count?: number;

    // Present only for professional buyers.
    professional_price?: string | null;
    professional_sale_price?: string | null;
    professional_effective_price?: number | null;
    min_order_qty_professional?: number;
};

export type Audience = 'individual' | 'professional';

/**
 * Price for one card.
 *
 * Retail stays the headline figure for *every* audience because retail is
 * what the checkout actually bills — `CheckoutService` has no notion of the
 * trade list yet. A professional additionally sees their reference rate
 * underneath, explicitly marked indicative, so the page never quotes a
 * number the basket won't honour.
 */
function PriceBlock({
    product,
    audience,
    money,
    t,
}: {
    product: ListingProduct;
    audience: Audience;
    money: (value: number | string) => string;
    t: (key: string) => string;
}) {
    // Demo-only state: see the `base_price` field comment above. Nothing
    // downstream (a discount, a trade rate) means anything without a retail
    // figure to apply it to, so this short-circuits before any of that.
    if (product.base_price === null) {
        return (
            <span className="text-xs text-souk-brown-deep/45 italic">
                {t('buyer.products.price_coming_soon')}
            </span>
        );
    }

    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = sale !== null && sale < base;
    const retail = onSale ? sale : base;

    const pro = product.professional_effective_price ?? null;
    const showPro = audience === 'professional' && pro !== null && pro < retail;
    const saving = showPro ? Math.round((1 - pro / retail) * 100) : 0;
    const minQty = product.min_order_qty_professional ?? 1;

    return (
        <div className="text-end">
            <div className="flex items-baseline justify-end gap-2">
                <span
                    className={`font-display text-lg leading-none ${onSale ? 'text-terracotta' : 'text-souk-title'}`}
                >
                    {money(retail)}
                    <span className="ms-1 text-[11px]">MAD</span>
                </span>
                {onSale && (
                    <span className="text-xs text-souk-brown-deep/45 line-through">
                        {money(base)}
                    </span>
                )}
            </div>

            {showPro && (
                <div
                    className="mt-1.5 inline-flex flex-wrap items-baseline justify-end gap-x-1.5 rounded-full bg-olive/10 px-2.5 py-1"
                    title={t('buyer.products.pro_price_hint')}
                >
                    <span className="text-[9px] tracking-[0.16em] text-olive/80 uppercase">
                        {t('buyer.products.pro_short')}
                    </span>
                    <span className="font-display text-sm leading-none text-olive">
                        {money(pro)}
                    </span>
                    {saving > 0 && (
                        <span className="text-[10px] font-medium text-olive">
                            −{saving}%
                        </span>
                    )}
                    {minQty > 1 && (
                        <span className="text-[10px] text-olive/70">
                            {t('buyer.products.pro_from_qty')} {minQty}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Arch-topped tile, echoing the horseshoe arches used on the home page and
 * in the `motif-arches` background — the shape is the brand's signature, and
 * keeps the grid free of the hard corners the old listing had.
 *
 * Shared by the product listing and a shop's own page rather than copied into
 * each: the price block below encodes which audience sees which figure, and a
 * second copy of that rule is exactly how one grid ends up quoting a trade
 * buyer a number the other doesn't.
 *
 * `showStore` is off on a shop's own page, where repeating the shop's name
 * under every tile says nothing.
 */
export function ProductCard({
    product,
    audience,
    money,
    t,
    showStore = true,
}: {
    product: ListingProduct;
    audience: Audience;
    money: (value: number | string) => string;
    t: (key: string) => string;
    showStore?: boolean;
}) {
    const cover = product.images[0]?.url;
    const base =
        product.base_price !== null ? Number(product.base_price) : null;
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = base !== null && sale !== null && sale < base;
    const soldOut = product.stock === 0;

    // What the caption says when the shop name is suppressed — otherwise a
    // tile on a shop page would carry an empty eyebrow line.
    const caption = showStore
        ? [product.store.store_name, product.category?.translated_name]
              .filter(Boolean)
              .join(' · ')
        : (product.category?.translated_name ?? '');

    return (
        <Link href={`/products/${product.slug}`} className="group block">
            <div className="relative aspect-[4/5] overflow-hidden rounded-t-[6rem] rounded-b-3xl bg-souk-brown/5 ring-1 ring-souk-brown/10 transition-all duration-500 group-hover:-translate-y-1 group-hover:shadow-xl group-hover:shadow-souk-brown/15 group-hover:ring-souk-brown/25">
                {cover ? (
                    <img
                        src={cover}
                        alt={product.name}
                        loading="lazy"
                        className={`h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05] ${soldOut ? 'opacity-60' : ''}`}
                    />
                ) : (
                    <div className="flex h-full w-full flex-col items-center justify-center gap-2 text-souk-brown/30">
                        <ZellijStarIcon className="h-8 w-8" />
                        <span className="text-[10px] tracking-[0.16em] uppercase">
                            {t('buyer.products.no_image')}
                        </span>
                    </div>
                )}

                {onSale && (
                    <span
                        className={`absolute start-4 top-4 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                    >
                        {t('buyer.products.badge_sale')}
                    </span>
                )}
                {soldOut && (
                    <span
                        className={`absolute end-4 top-4 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                    >
                        {t('buyer.products.badge_sold_out')}
                    </span>
                )}
            </div>

            <div className="mt-4 px-1">
                {caption && (
                    <p
                        className={`truncate text-souk-brown-deep/55 ${EYEBROW}`}
                    >
                        {caption}
                    </p>
                )}

                <div className="mt-2 flex items-start justify-between gap-3">
                    <h3 className="font-display text-lg leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                        {product.name}
                    </h3>
                    <div className="shrink-0">
                        <PriceBlock
                            product={product}
                            audience={audience}
                            money={money}
                            t={t}
                        />
                    </div>
                </div>
            </div>
        </Link>
    );
}

export default ProductCard;
