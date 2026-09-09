import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import VaseIcon from '@/components/home/VaseIcon';
import { useHoverGallery } from '@/hooks/use-hover-gallery';

export type RailProduct = {
    id: number;
    name: string;
    slug: string;
    /**
     * Null while an admin has catalogued the piece but not yet agreed its
     * price — see `Product::scopeStorefront()`.
     */
    base_price: number | string | null;
    sale_price: number | string | null;
    category: { id: number; translated_name: string } | null;
    store: { store_name: string | null; city_name: string | null };
    images: { id: number; url: string; type: string }[];
};

/**
 * One card in the rail.
 *
 * Split out from the `.map()` in `ProductRail` below rather than left inline:
 * `useHoverGallery` is a hook, and hooks may only run at the top level of a
 * component — calling one from inside a loop callback is exactly the
 * "different hook order between renders" case the Rules of Hooks exist to
 * catch, since it would run once per item rather than being tied to one.
 */
function RailCard({
    product,
    currency,
}: {
    product: RailProduct;
    currency: string;
}) {
    const { index, previous, hoverProps } = useHoverGallery(
        product.images.length,
    );

    const base =
        product.base_price !== null ? Number(product.base_price) : null;
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = base !== null && sale !== null && sale < base;

    return (
        <Link
            {...hoverProps}
            href={`/products/${product.slug}`}
            className="group block"
        >
            <div className="relative aspect-[4/5] overflow-hidden rounded-t-[8rem] rounded-b-2xl bg-souk-brown/5">
                {product.images.length > 0 ? (
                    // Same dip-free crossfade stack as `CollectionCard` and
                    // the home hero's `HeroBackdrop` — see either for why the
                    // outgoing frame stays opaque underneath the incoming one
                    // rather than the two simply swapping opacity together.
                    <div className="absolute inset-0 isolate">
                        {product.images.map((image, i) => {
                            const active = i === index;
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
                                        alt={i === 0 ? product.name : ''}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                    />
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <VaseIcon className="h-14 w-14 text-souk-brown/25" />
                    </div>
                )}
                <div
                    aria-hidden="true"
                    className="absolute inset-0 rounded-t-[8rem] rounded-b-2xl ring-1 ring-souk-brown/10 ring-inset"
                />
            </div>

            <div className="mt-4">
                {product.store.city_name && (
                    <p className="text-[10px] tracking-[0.18em] text-souk-brown/70 uppercase">
                        {product.store.city_name}
                    </p>
                )}
                <h3 className="mt-1.5 line-clamp-1 font-display text-base leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                    {product.name}
                </h3>
                <div className="mt-1.5 flex items-baseline gap-2">
                    {base !== null ? (
                        <>
                            <span className="font-display text-[15px] text-souk-brown">
                                {onSale ? sale : base}{' '}
                                <span className="text-[10px]">{currency}</span>
                            </span>
                            {onSale && (
                                <span className="text-xs text-souk-brown-deep/45 line-through">
                                    {base}
                                </span>
                            )}
                        </>
                    ) : (
                        <span className="text-xs text-souk-brown-deep/45">
                            —
                        </span>
                    )}
                </div>
                {product.store.store_name && (
                    <p className="mt-1 line-clamp-1 text-xs text-souk-brown-deep/60">
                        {product.store.store_name}
                    </p>
                )}
            </div>
        </Link>
    );
}

/**
 * The home page's product strip: one horizontal row you page through with
 * arrows, rather than a grid of four.
 *
 * Native scroll (`overflow-x-auto` + `scroll-snap`) with the arrows driving
 * `scrollBy`, not a transform-based track. That buys touch swipe, trackpad
 * flick, keyboard and screen-reader traversal for free, all of which a
 * translated track would have had to reimplement — and it mirrors correctly
 * under `dir="rtl"` without any special casing, because the browser flips
 * the scroll axis itself.
 *
 * The arrows are the one place RTL does need handling: `scrollLeft` runs
 * negative in a right-to-left container, so "next" is a *negative* delta
 * there — which is what `isRTL` flips in `page()` below.
 */
export default function ProductRail({
    products,
    isRTL,
    currency,
    prevLabel,
    nextLabel,
}: {
    products: RailProduct[];
    isRTL: boolean;
    /** Already-translated currency suffix, e.g. "MAD". */
    currency: string;
    prevLabel: string;
    nextLabel: string;
}) {
    const track = useRef<HTMLUListElement>(null);
    const [atStart, setAtStart] = useState(true);
    const [atEnd, setAtEnd] = useState(false);

    /**
     * Which arrows are still live. Recomputed on scroll and on resize rather
     * than derived from an index, because the number of visible cards changes
     * with the breakpoint and a flick can land anywhere between them.
     *
     * `Math.abs` on scrollLeft: right-to-left containers report it as a
     * negative offset from the right edge in every current browser.
     */
    useEffect(() => {
        const el = track.current;

        if (!el) {
            return;
        }

        const update = () => {
            const offset = Math.abs(el.scrollLeft);
            // 2px of slack — sub-pixel layout means the extremes rarely land
            // exactly on 0 or on the full scroll width.
            const max = el.scrollWidth - el.clientWidth - 2;

            setAtStart(offset <= 2);
            setAtEnd(offset >= max);
        };

        update();
        el.addEventListener('scroll', update, { passive: true });

        const observer = new ResizeObserver(update);
        observer.observe(el);

        return () => {
            el.removeEventListener('scroll', update);
            observer.disconnect();
        };
    }, [products.length]);

    const page = (dir: 1 | -1) => {
        const el = track.current;

        if (!el) {
            return;
        }

        // Just under a full viewport of the track, so the card at the edge
        // stays partly visible and the row reads as continuing rather than
        // as a set of discrete pages.
        const delta = el.clientWidth * 0.85 * dir * (isRTL ? -1 : 1);

        el.scrollBy({ left: delta, behavior: 'smooth' });
    };

    const arrow =
        'grid h-11 w-11 place-items-center rounded-full border border-souk-brown/25 text-souk-brown transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:pointer-events-none disabled:opacity-30';

    return (
        <div className="relative">
            <ul
                ref={track}
                className="-mx-5 flex snap-x snap-mandatory gap-6 overflow-x-auto px-5 pb-2 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden"
            >
                {products.map((product) => (
                    <li
                        key={product.id}
                        /* Fixed widths rather than a fraction of the track: a
                           percentage basis would resize the cards as the
                           viewport grows, whereas a rail should keep the
                           card constant and simply show more of them. */
                        className="w-[15rem] shrink-0 snap-start sm:w-[16.5rem]"
                    >
                        <RailCard product={product} currency={currency} />
                    </li>
                ))}
            </ul>

            {/*
             * Below the rail rather than floating over its edges: overlaid
             * arrows sit on top of the first and last card, and on a narrow
             * screen there is not enough gutter for them to clear the
             * artwork.
             *
             * `rtl:rotate-180` on the glyphs because a chevron is a
             * direction, not an ornament. Under `dir="rtl"` flex reverses the
             * row, so the "previous" button moves to the right-hand side —
             * and in a right-to-left rail previous *is* rightward, so an
             * unrotated ChevronLeft would sit on the right pointing left,
             * against its own travel.
             */}
            <div className="mt-10 flex items-center justify-center gap-3">
                <button
                    type="button"
                    onClick={() => page(-1)}
                    disabled={atStart}
                    aria-label={prevLabel}
                    className={arrow}
                >
                    <ChevronLeft
                        className="h-5 w-5 rtl:rotate-180"
                        strokeWidth={1.5}
                    />
                </button>
                <button
                    type="button"
                    onClick={() => page(1)}
                    disabled={atEnd}
                    aria-label={nextLabel}
                    className={arrow}
                >
                    <ChevronRight
                        className="h-5 w-5 rtl:rotate-180"
                        strokeWidth={1.5}
                    />
                </button>
            </div>
        </div>
    );
}
