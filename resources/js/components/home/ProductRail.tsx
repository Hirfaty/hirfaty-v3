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
    /**
     * Own images plus every variant's, deduplicated — present only when the
     * backend eager-loaded variant media (the home rail does). Falls back to
     * `images` below when absent, so a product with no variants (or fetched
     * through a path that skips the eager load) still shows its own photos.
     */
    gallery_images?: { id: number; url: string; type: string }[];
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
    tabIndex,
}: {
    product: RailProduct;
    currency: string;
    /**
     * -1 for the drifting strip's second, decorative copy — see
     * `ProductRail`'s loop comment. Keeps a Tab press from landing on a card
     * that is about to slide back over its own original.
     */
    tabIndex?: number;
}) {
    const gallery = product.gallery_images ?? product.images;
    const { index, previous, hoverProps } = useHoverGallery(gallery.length);

    const base =
        product.base_price !== null ? Number(product.base_price) : null;
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = base !== null && sale !== null && sale < base;

    return (
        <Link
            {...hoverProps}
            href={`/products/${product.slug}`}
            tabIndex={tabIndex}
            className="group block"
        >
            <div className="relative aspect-[4/5] overflow-hidden rounded-xl bg-souk-brown/5">
                {gallery.length > 0 ? (
                    // Same dip-free crossfade stack as `CollectionCard` and
                    // the home hero's `HeroBackdrop` — see either for why the
                    // outgoing frame stays opaque underneath the incoming one
                    // rather than the two simply swapping opacity together.
                    <div className="absolute inset-0 isolate">
                        {gallery.map((image, i) => {
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
                    className="absolute inset-0 ring-1 ring-souk-brown/10 ring-inset"
                />
            </div>

            <div className="mt-4">
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
 * The home page's product strip.
 *
 * Drifts on its own exactly like the "Galerie" strip further down the page
 * (`animate-gallery` — a doubled track translated by CSS) — except this row
 * is real, clickable merchandise rather than decorative photography, so the
 * loop is driven from JS against genuine `scrollLeft` instead of a CSS
 * transform: that keeps native touch swipe, trackpad flick and keyboard
 * traversal all still working the instant the drift is stopped, which a
 * transform-only marquee couldn't offer without being torn down and rebuilt
 * as a scroller at that moment.
 *
 * The loop illusion itself borrows the Gallery's own trick: the track holds
 * two identical copies of the list back to back, drifts forward, and snaps
 * `scrollLeft` back by exactly one copy's width the instant it crosses into
 * the second — invisible, because the second copy looks exactly like the
 * first taking its place. The second copy is `aria-hidden` and untabbable,
 * same as the Gallery's, and only exists while the drift is running: once
 * stopped it unmounts, so a shopper paging by hand never lands on a
 * duplicate.
 *
 * Any manual touch — an arrow, a swipe, a wheel nudge — stops the drift for
 * good rather than pausing it. A marquee that resumes out from under a
 * shopper mid-swipe is fighting them; once they have taken the wheel, native
 * scroll-snap paging (the pre-existing behaviour) takes over permanently.
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

    /*
     * Off outright for anyone who has asked their OS for reduced motion, and
     * for a strip too short to loop meaningfully — same floor `useHoverGallery`
     * applies per-card. Read once: this only reflects the setting in place
     * when the page loaded, matching the `motion-safe:` variant the CSS
     * marquee relies on elsewhere.
     */
    const [autoplay, setAutoplay] = useState(
        () =>
            products.length > 1 &&
            !(
                typeof window !== 'undefined' &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ),
    );
    // Paused (not stopped) while the pointer is over the strip, exactly like
    // the Gallery's `hover:[animation-play-state:paused]` — read inside the
    // rAF loop via a ref rather than state, so hovering doesn't re-run the
    // effect that owns the animation frame.
    const paused = useRef(false);

    /**
     * Ends the drift for good and drops the decorative second copy. Safe to
     * call unconditionally — every trigger below fires this before doing
     * anything else, and a no-op when the drift is already off.
     *
     * No scroll-position bookkeeping needed: the rAF loop below always keeps
     * `scrollLeft` within the span of one copy (it is what the wraparound is
     * for), so the position already makes sense once the second copy is gone
     * — nothing to translate.
     */
    const stopAutoplay = () => setAutoplay(false);

    /**
     * Drives the loop while `autoplay` is on: advances `scrollLeft` at a
     * constant speed (delta-time based, so frame-rate hiccups don't change
     * how fast the row appears to move) and folds it back by one copy's
     * width the moment it drifts into the second — the seamless-loop trick,
     * done against real scroll instead of a transform.
     *
     * `isRTL` flips the sign the same way `page()` used to: right-to-left
     * containers report `scrollLeft` counting *down* from 0 in every current
     * browser, so "forward" is a negative delta there.
     */
    useEffect(() => {
        if (!autoplay) {
            return;
        }

        const el = track.current;

        if (!el) {
            return;
        }

        const pxPerSecond = 26;
        const dir = isRTL ? -1 : 1;
        let raf = 0;
        let lastTs: number | null = null;

        const step = (ts: number) => {
            // Clamped rather than left raw: a backgrounded tab can deliver a
            // multi-second gap between frames, and applying that gap in one
            // jump would skip the row past several cards instead of drifting.
            const dt = lastTs === null ? 0 : Math.min(ts - lastTs, 100) / 1000;
            lastTs = ts;

            if (!paused.current) {
                el.scrollLeft += dir * pxPerSecond * dt;

                const halfway = el.scrollWidth / 2;

                if (dir > 0 && el.scrollLeft >= halfway) {
                    el.scrollLeft -= halfway;
                } else if (dir < 0 && el.scrollLeft <= -halfway) {
                    el.scrollLeft += halfway;
                }
            }

            raf = requestAnimationFrame(step);
        };

        raf = requestAnimationFrame(step);

        return () => cancelAnimationFrame(raf);
    }, [autoplay, isRTL, products.length]);

    /**
     * Which arrows are still live. Only meaningful once the drift has
     * stopped — while it's running the strip loops forever, so there is no
     * "start" or "end" to disable against yet.
     *
     * `Math.abs` on scrollLeft: right-to-left containers report it as a
     * negative offset from the right edge in every current browser.
     */
    useEffect(() => {
        const el = track.current;

        if (!el || autoplay) {
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
    }, [products.length, autoplay]);

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

    /**
     * An arrow's first job is to hand control back to the shopper: while the
     * drift is running, a click stops it and nothing else — the row is
     * already moving in that general direction, so a click that both stopped
     * *and* paged would fire two motions on top of each other. Once stopped,
     * arrows page as normal.
     */
    const handleArrow = (dir: 1 | -1) => {
        if (autoplay) {
            stopAutoplay();

            return;
        }

        page(dir);
    };

    /*
     * Overlaid on the rail's own edges, so they read as belonging to the row
     * rather than as a control panel parked under it. Solid paper fill and a
     * shadow because they now sit over photography, where the bare outlined
     * button they used to be would disappear into a dark image.
     *
     * `top` is half of a card's *image*, not half of the track: the track is
     * taller by the caption underneath each card, and centring on that would
     * leave both arrows visibly low. The figures are the card widths below
     * (15 / 16.5rem) at the 4:5 crop, halved.
     */
    const arrow =
        'absolute top-[9.375rem] z-20 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-souk-paper text-souk-brown shadow-lg shadow-souk-brown/15 ring-1 ring-souk-brown/15 transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:pointer-events-none disabled:opacity-0 sm:top-[10.3125rem] sm:grid';

    return (
        <div className="relative">
            {/*
             * The row bleeds past the container on both sides and is faded
             * back out there, so the cards run off the edge of the page
             * instead of stopping dead in a line — the rail should look like
             * a slice of something longer, which is what the arrows promise.
             *
             * Scroll-snap only applies once the drift has stopped: snapping
             * against a `scrollLeft` that JS is already driving every frame
             * fights the browser's own snap-back and stutters.
             *
             * Any manual scroll gesture on the track — a wheel nudge, a
             * touch drag — stops the drift the same way an arrow does,
             * before the browser's own handling of that gesture even runs.
             */}
            <ul
                ref={track}
                onMouseEnter={() => {
                    paused.current = true;
                }}
                onMouseLeave={() => {
                    paused.current = false;
                }}
                onWheel={stopAutoplay}
                onTouchStart={stopAutoplay}
                onPointerDown={stopAutoplay}
                className={`-mx-5 flex gap-6 overflow-x-auto px-5 pb-2 [scrollbar-width:none] sm:-mx-10 sm:px-10 [&::-webkit-scrollbar]:hidden ${
                    autoplay
                        ? ''
                        : /* `scroll-ps-*` has to match the padding: the
                             snapport is the padding *box*, so without it the
                             browser scrolls the gutter away to snap the first
                             card flush — parking it under the fade before
                             anyone has touched the rail. */
                          'snap-x snap-mandatory scroll-ps-5 sm:scroll-ps-10'
                }`}
            >
                {/*
                 * One copy while stopped; two back to back while drifting —
                 * see the component doc for why. The second is `aria-hidden`
                 * and untabbable, same treatment the Gallery gives its own
                 * repeated copy just below on the page.
                 */}
                {(autoplay ? [0, 1] : [0]).map((copy) =>
                    products.map((product) => (
                        <li
                            key={`${copy}-${product.id}`}
                            aria-hidden={copy === 1 || undefined}
                            /* Fixed widths rather than a fraction of the
                               track: a percentage basis would resize the
                               cards as the viewport grows, whereas a rail
                               should keep the card constant and simply show
                               more of them. */
                            className="w-[15rem] shrink-0 snap-start sm:w-[16.5rem]"
                        >
                            <RailCard
                                product={product}
                                currency={currency}
                                tabIndex={copy === 1 ? -1 : undefined}
                            />
                        </li>
                    )),
                )}
            </ul>

            {/*
             * The fades sit on the bleed itself, so a card leaving the rail
             * dissolves into the page rather than being sliced off by the
             * section's `overflow-hidden`. `pointer-events-none` keeps them
             * out of the way of a swipe that starts near the edge.
             */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 -start-5 z-10 w-12 bg-gradient-to-r from-souk-paper to-transparent sm:-start-10 sm:w-16 rtl:bg-gradient-to-l"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 -end-5 z-10 w-12 bg-gradient-to-l from-souk-paper to-transparent sm:-end-10 sm:w-16 rtl:bg-gradient-to-r"
            />

            {/*
             * Logical `start`/`end` rather than left/right: "previous" belongs
             * on whichever side the row starts from, which mirrors with the
             * document. The glyphs take `rtl:rotate-180` for the same reason —
             * a chevron is a direction, not an ornament, and in a
             * right-to-left rail previous *is* rightward.
             *
             * Hidden below `sm`, where there is no gutter for them to clear
             * the artwork and swiping is the natural gesture anyway. Never
             * disabled while the drift is running — there is no "end" to
             * disable against yet, and a click's job at that point is only to
             * stop it. At the ends of a stopped rail they fade out instead of
             * greying: a button sitting over a photograph at 30% opacity
             * looks like a rendering fault.
             */}
            <button
                type="button"
                onClick={() => handleArrow(-1)}
                disabled={!autoplay && atStart}
                aria-label={prevLabel}
                className={`${arrow} -start-4`}
            >
                <ChevronLeft
                    className="h-5 w-5 rtl:rotate-180"
                    strokeWidth={1.5}
                />
            </button>
            <button
                type="button"
                onClick={() => handleArrow(1)}
                disabled={!autoplay && atEnd}
                aria-label={nextLabel}
                className={`${arrow} -end-4`}
            >
                <ChevronRight
                    className="h-5 w-5 rtl:rotate-180"
                    strokeWidth={1.5}
                />
            </button>
        </div>
    );
}
