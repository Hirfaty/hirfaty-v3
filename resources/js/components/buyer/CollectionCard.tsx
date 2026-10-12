import { Link, router, usePage } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { useState } from 'react';
import type { Audience, ListingProduct } from '@/components/buyer/ProductCard';
import TaxIncluded from '@/components/buyer/TaxIncluded';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import { useHoverGallery } from '@/hooks/use-hover-gallery';

/** Museum-caption label, shared with the page this card sits on. */
const EYEBROW =
    'text-[9px] font-semibold uppercase tracking-[0.16em] sm:text-[10px]';

/**
 * A tile for the "Objects with a story" grid. The one product tile every
 * buyer-facing grid uses: the marketplace listing, new arrivals, and a
 * shop's own page all render this, so a shop page reads as a filtered view
 * of the same catalogue rather than a visually distinct product.
 *
 * `ProductCard` (a cream-on-brown panel, `@/components/buyer/ProductCard`)
 * is this component's predecessor and is no longer rendered anywhere — only
 * its `Audience`/`ListingProduct` types are still imported from that module.
 * Left in place rather than deleted since nobody has asked for it to go.
 *
 * Plain rectangles, deliberately. The frame is still doubled — an outer paper
 * mat and an inner border holding the photograph — but the `portal-frame`
 * arch both used to carry is gone: it cropped the top of every photograph
 * into the same silhouette, which cost the merchandise its own shape and
 * forced the badges down off the top edge to stay out of the curve.
 */
export function CollectionCard({
    product,
    audience,
    money,
    t,
    wishlisted,
    canWishlist,
}: {
    product: ListingProduct;
    audience: Audience;
    money: (value: number | string) => string;
    t: (key: string) => string;
    /** Whether the signed-in buyer already has this piece on their list. */
    wishlisted: boolean;
    /** False for guests and for non-buyer roles, whom the route would reject. */
    canWishlist: boolean;
}) {
    const [wished, setWished] = useState(wishlisted);
    const { url } = usePage();
    const gallery = product.gallery_images ?? product.images;
    const { index, previous, hoverProps } = useHoverGallery(gallery.length);

    const base =
        product.base_price !== null ? Number(product.base_price) : null;
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = base !== null && sale !== null && sale < base;
    // `in_stock`, not `stock`: a variant product's own stock column is always
    // 0 by design (availability lives on each variant), so reading the column
    // marks every such piece sold out.
    const soldOut = !product.in_stock;
    const discount = onSale ? Math.round((1 - sale / base) * 100) : 0;

    // Craft, then where it was made — "Ceramics · Fes". The shop's own name is
    // left to the detail page; at grid scale the region is what tells one
    // tile apart from the next.
    const caption = [product.category?.translated_name, product.store.city_name]
        .filter(Boolean)
        .join(' · ');

    const pro = product.professional_effective_price ?? null;
    const retail = onSale ? sale : base;
    const showPro =
        audience === 'professional' &&
        pro !== null &&
        retail !== null &&
        pro < retail;

    const toggleWishlist = () => {
        // The route sits behind `role:buyer`, so a guest would only be
        // bounced — send them to log in rather than fire a request that
        // cannot succeed.
        if (!canWishlist) {
            // Come back to this listing (filters included) once signed in.
            router.visit(route('login', { redirect: url }));

            return;
        }

        // Optimistic, rolled back if the request fails.
        setWished((w) => !w);
        router.post(
            route('products.wishlist', product.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setWished((w) => !w),
            },
        );
    };

    return (
        <article
            {...hoverProps}
            className="group relative min-w-0 transition-transform duration-500 ease-out hover:-translate-y-1"
        >
            <div className="relative overflow-hidden rounded-md border border-souk-brown/25 bg-souk-paper p-1.5 transition-[border-color,box-shadow] duration-500 group-hover:border-souk-brown/60 group-hover:shadow-[0_18px_45px_-30px_var(--color-souk-brown)] sm:p-2">
                <div className="relative aspect-4/5 overflow-hidden border border-souk-brown/20 bg-white">
                    {gallery.length > 0 ? (
                        /* `isolate` scopes the stack's own z-index ranking to
                           itself, so it can't compete with the wishlist
                           button's `z-10` (which exists for an unrelated
                           reason — see its comment) or leak past this box at
                           all. Being the first child already puts it under
                           every later sibling in normal DOM order, so the
                           isolation is a safety net rather than load-bearing
                           today — but a stack that can silently climb above
                           its own badges the next time someone edits either
                           block is exactly the kind of bug that isn't caught
                           until a screenshot review, so it stays explicit. */
                        <div className="absolute inset-0 isolate">
                            {gallery.map((image, i) => {
                                const active = i === index;
                                // Stays fully opaque *underneath* the
                                // incoming frame while it fades in, so the
                                // crossfade never dips through a moment where
                                // both layers are half-transparent and the
                                // tile visibly darkens — same technique as
                                // `ProductShowcase`'s own cards.
                                const outgoing = i === previous && i !== index;

                                return (
                                    <div
                                        key={image.id}
                                        className={`absolute inset-0 ${
                                            active
                                                ? 'z-20 opacity-100 transition-opacity duration-500 ease-out'
                                                : outgoing
                                                  ? 'z-10 opacity-100'
                                                  : 'z-0 opacity-0'
                                        }`}
                                    >
                                        <img
                                            src={image.url}
                                            // One description per card is
                                            // enough — a screen reader
                                            // linearises the DOM once, not
                                            // per hover frame, so the other
                                            // layers would only repeat it.
                                            alt={i === 0 ? product.name : ''}
                                            loading="lazy"
                                            className={`h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.045] ${soldOut ? 'opacity-55' : ''}`}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="flex h-full w-full flex-col items-center justify-center gap-2 text-souk-brown/25">
                            <ZellijStarIcon className="h-8 w-8" />
                            <span className={EYEBROW}>
                                {t('buyer.products.no_image')}
                            </span>
                        </div>
                    )}

                    {/* Back at the corner now the arch no longer clips it —
                        the 19% inset these used to carry only existed to keep
                        them clear of the curve. */}
                    {soldOut ? (
                        <span
                            className={`absolute start-2 top-2 border border-souk-brown/25 bg-souk-paper/92 px-1.5 py-1 text-souk-brown-deep sm:start-3 sm:top-3 ${EYEBROW}`}
                        >
                            {t('buyer.products.badge_sold_out')}
                        </span>
                    ) : (
                        discount > 0 && (
                            <span
                                className={`absolute start-2 top-2 border border-terracotta/30 bg-souk-paper/92 px-1.5 py-1 text-terracotta-deep sm:start-3 sm:top-3 ${EYEBROW}`}
                            >
                                −{discount}%
                            </span>
                        )
                    )}

                    {/* z-10 to clear the stretched link's ::after below. */}
                    <button
                        type="button"
                        onClick={toggleWishlist}
                        aria-pressed={wished}
                        aria-label={t(
                            wished
                                ? 'buyer.products.wishlist_remove'
                                : 'buyer.products.wishlist_add',
                        )}
                        className="absolute end-2 top-2 z-10 grid h-9 w-9 cursor-pointer place-items-center bg-souk-paper/90 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream sm:end-3 sm:top-3"
                    >
                        <Heart
                            className={`h-4 w-4 ${wished ? 'fill-terracotta text-terracotta' : ''}`}
                            strokeWidth={1.5}
                        />
                    </button>

                    {/* Not a control — the whole tile already links to the
                        piece. It is a hover affordance telling you so. */}
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute bottom-3 left-1/2 hidden -translate-x-1/2 border border-souk-brown bg-souk-brown px-3 py-1.5 text-xs text-souk-cream opacity-0 transition-opacity duration-300 group-hover:opacity-100 sm:block"
                    >
                        {t('buyer.products.view_product')}
                    </span>
                </div>
            </div>

            <div className="px-0.5 pt-4 sm:pt-5">
                {caption && (
                    <p
                        className={`mb-1 truncate text-terracotta-deep ${EYEBROW}`}
                    >
                        {caption}
                    </p>
                )}

                <h3 className="font-display text-base leading-[1.15] text-souk-title sm:text-xl">
                    {/* Stretched link: the tile is one click target without
                        nesting the heart button inside an anchor. */}
                    <Link
                        href={route('products.show', product.slug)}
                        className="after:absolute after:inset-0 after:content-['']"
                    >
                        {product.name}
                    </Link>
                </h3>

                {/* Clamped rather than measured: `line-clamp-3` sets
                    `-webkit-line-clamp`, which every current browser already
                    renders with a trailing ellipsis when the text overflows
                    the third line — no manual "…" needed. Still hidden below
                    `sm`, where the grid drops to two columns and a card has
                    no room to spare for three lines of body copy.
                    No `sm:block` here: Tailwind emits `.block` right after
                    `.line-clamp-3` in the utilities layer, so at equal
                    specificity it would win the cascade and silently reset
                    `display` back from `-webkit-box` to `block` — which
                    disables the clamp entirely while leaving the `-webkit-
                    line-clamp` declaration inert. `line-clamp-3` already sets
                    its own non-`none` display, so it needs no help clearing
                    `hidden`. */}
                {product.description_text && (
                    <p className="mt-2 hidden text-xs leading-relaxed text-souk-brown-deep/75 sm:line-clamp-3">
                        {product.description_text}
                    </p>
                )}

                <div className="mt-2.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5 sm:mt-3">
                    {base === null ? (
                        // Demo-only state: the catalogue currently lists pieces
                        // an admin has not yet priced. See ListingProduct.
                        <span className="text-xs text-souk-brown-deep/60">
                            {t('buyer.products.price_coming_soon')}
                        </span>
                    ) : (
                        <>
                            <span className="text-sm font-semibold text-souk-brown">
                                {money(retail as number)}
                                <TaxIncluded />
                            </span>
                            {onSale && (
                                <span className="text-xs text-souk-brown-deep/50 line-through">
                                    {money(base)}
                                </span>
                            )}
                        </>
                    )}
                </div>

                {/* Retail stays the headline for every audience because retail
                    is what CheckoutService bills; a trade buyer additionally
                    sees their reference rate, marked as such. */}
                {showPro && (
                    <p
                        className="mt-1.5 text-[10px] tracking-[0.12em] text-olive uppercase"
                        title={t('buyer.products.pro_price_hint')}
                    >
                        {t('buyer.products.pro_short')} {money(pro)}
                        <TaxIncluded />
                    </p>
                )}
            </div>
        </article>
    );
}

export default CollectionCard;
