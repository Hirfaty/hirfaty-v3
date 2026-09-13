import { Link } from '@inertiajs/react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
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
                    // Same dip-free crossfade stack as `CollectionCard` — see
                    // it for why the outgoing frame stays opaque underneath
                    // the incoming one rather than the two simply swapping
                    // opacity together.
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

/** Imperative paging, for the arrow pair the page header renders — see below. */
export type ProductRailHandle = {
    prev: () => void;
    next: () => void;
};

/**
 * Enough for a caller's own buttons to disable themselves correctly — the
 * same rule the rail used to apply to its own, now-removed arrows: while
 * autoplay is running there is no "start" or "end" to disable against, so
 * both stay enabled regardless of `atStart`/`atEnd`.
 */
export type ProductRailBounds = {
    atStart: boolean;
    atEnd: boolean;
    autoplay: boolean;
};

/**
 * The home page's product strip — two rows of merchandise, packed into
 * columns of two cards each so a single horizontal scroll moves both rows
 * together. Two rows rather than one so the same width of screen surfaces
 * twice the stock before a shopper has to touch anything.
 *
 * Moves itself in hops rather than a continuous drift: advance a few
 * columns, settle, wait, advance again — a rhythm rather than a scroll, so it
 * reads as merchandise being paged through rather than a marquee. Driven
 * against genuine `scrollLeft` rather than a CSS transform (the same choice
 * the old single-row drift made), which is what keeps native touch swipe,
 * trackpad flick and keyboard traversal all still working the instant
 * autoplay is stopped, without tearing the strip down and rebuilding it as a
 * scroller at that moment.
 *
 * The loop illusion borrows the Gallery's own trick: the track holds two
 * identical copies of the column list back to back, hops forward, and snaps
 * `scrollLeft` back by exactly one copy's width the instant it crosses into
 * the second — invisible, because the second copy looks exactly like the
 * first taking its place. The second copy is `aria-hidden` and untabbable,
 * same as the Gallery's, and only exists while autoplay is running: once
 * stopped it unmounts, so a shopper paging by hand never lands on a
 * duplicate.
 *
 * Only the arrows stop it, and for good — not a wheel nudge, not a touch that
 * merely started over the strip, and not the pointer simply resting on it.
 * Those used to stop or pause it, which is exactly what made the rail sit
 * still for anyone reading it: the cursor ends up over the strip the moment
 * someone scrolls *the page* past it, wherever the pointer happens to be, and
 * a rail that freezes whenever it is being looked at never gets to show its
 * second handful of stock. Hovering a card still swaps its own gallery
 * (`useHoverGallery`) — the strip underneath keeps hopping regardless.
 *
 * The arrows themselves are not rendered here. They used to sit overlaid on
 * the rail's own edges; the page header now renders its own pair top-right of
 * the section instead, driving this component through `ref` (`prev`/`next`)
 * and reading `onBoundsChange` to know when to grey out. The rail keeps
 * owning the scroll position, the hop loop and the autoplay-stopping rule —
 * only the buttons' markup and placement moved.
 */
const ProductRail = forwardRef<
    ProductRailHandle,
    {
        products: RailProduct[];
        isRTL: boolean;
        /** Already-translated currency suffix, e.g. "MAD". */
        currency: string;
        /** Fires whenever the paging state changes — see `ProductRailBounds`. */
        onBoundsChange?: (bounds: ProductRailBounds) => void;
    }
>(function ProductRail({ products, isRTL, currency, onBoundsChange }, ref) {
    const track = useRef<HTMLUListElement>(null);
    const [atStart, setAtStart] = useState(true);
    const [atEnd, setAtEnd] = useState(false);

    /*
     * Paired up two at a time into columns, so the track scrolls one strip
     * of *columns* rather than one strip of cards — each column stacks its
     * two products vertically, which is what turns a single-row rail into a
     * two-row one without touching how the scrolling itself works. A
     * trailing odd product gets a column of its own rather than being
     * dropped, since the backend already decides how many to feature.
     */
    const columns: RailProduct[][] = [];

    for (let i = 0; i < products.length; i += 2) {
        columns.push(products.slice(i, i + 2));
    }

    /*
     * Off outright for anyone who has asked their OS for reduced motion, and
     * for a strip too short to loop meaningfully — same floor `useHoverGallery`
     * applies per-card. Read once: this only reflects the setting in place
     * when the page loaded, matching the `motion-safe:` variant the CSS
     * marquee relies on elsewhere.
     */
    const [autoplay, setAutoplay] = useState(
        () =>
            columns.length > 1 &&
            !(
                typeof window !== 'undefined' &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ),
    );

    /**
     * Ends autoplay for good and drops the decorative second copy. Only the
     * arrows call this — see the component doc for why every other gesture
     * used to and no longer does.
     *
     * No scroll-position bookkeeping needed: the loop below always keeps
     * `scrollLeft` within the span of one copy (it is what the wraparound is
     * for), so the position already makes sense once the second copy is gone
     * — nothing to translate.
     */
    const stopAutoplay = () => setAutoplay(false);

    /**
     * Drives the loop while `autoplay` is on: tween several columns' worth of
     * `scrollLeft`, rest, repeat — folding back by one copy's width the
     * moment a hop crosses into the second copy, the same seamless-loop trick
     * the old continuous drift used, just applied once per hop instead of
     * once per frame.
     *
     * `HOP_COLUMNS` columns rather than one: a hop that only ever swapped in
     * a single new column read as barely moving — a shopper glancing over
     * mid-hop would see almost the same set of products as before it. Moving
     * a small page's worth at once makes each hop read as a new handful of
     * merchandise, the way the arrows' own `page()` already does for a
     * manual press.
     *
     * The column width is measured off the live DOM at the start of every
     * hop rather than computed once: it changes at the `sm` breakpoint, and
     * re-measuring is the only way the hop distance keeps up with a resize
     * that happens between hops. The result is clamped to half the track
     * (`halfway`, the span of one copy of the list): without it, a short
     * catalogue — few enough columns that `HOP_COLUMNS` overshoots a whole
     * copy — could skip past the wraparound point the check below only
     * tests once per frame, rather than land inside it.
     *
     * `isRTL` flips the sign the same way `page()` does: right-to-left
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

        const dir = isRTL ? -1 : 1;
        // How long one hop takes, and how long the strip rests before the
        // next — together, the "move a little, stop" rhythm.
        const hopDuration = 650;
        const dwell = 1500;
        const HOP_COLUMNS = 3;

        let raf = 0;
        let timeout: ReturnType<typeof setTimeout> | undefined;
        let cancelled = false;

        const hop = () => {
            if (cancelled) {
                return;
            }

            const first = el.children[0] as HTMLElement | undefined;
            const second = el.children[1] as HTMLElement | undefined;
            const columnStep =
                first && second
                    ? Math.abs(second.offsetLeft - first.offsetLeft)
                    : el.clientWidth;
            const halfway = el.scrollWidth / 2;
            const distancePerHop = Math.min(columnStep * HOP_COLUMNS, halfway);

            const from = el.scrollLeft;
            const travel = dir * distancePerHop;
            const start = performance.now();

            const frame = (now: number) => {
                if (cancelled) {
                    return;
                }

                const p = Math.min((now - start) / hopDuration, 1);
                // easeOutCubic — the hop decelerates into its rest rather
                // than stopping dead.
                const eased = 1 - (1 - p) ** 3;

                el.scrollLeft = from + travel * eased;

                // `halfway` is the outer, per-hop measurement above — the
                // track's width does not change mid-tween, so there is
                // nothing to re-measure here.
                if (dir > 0 && el.scrollLeft >= halfway) {
                    el.scrollLeft -= halfway;
                } else if (dir < 0 && el.scrollLeft <= -halfway) {
                    el.scrollLeft += halfway;
                }

                if (p < 1) {
                    raf = requestAnimationFrame(frame);
                } else {
                    timeout = setTimeout(hop, dwell);
                }
            };

            raf = requestAnimationFrame(frame);
        };

        timeout = setTimeout(hop, dwell);

        return () => {
            cancelled = true;
            cancelAnimationFrame(raf);
            clearTimeout(timeout);
        };
    }, [autoplay, isRTL, columns.length]);

    /**
     * Which arrows are still live. Only meaningful once autoplay has
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
    }, [columns.length, autoplay]);

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
     * An arrow's first job is to hand control back to the shopper: while
     * autoplay is running, a press stops it and nothing else — the row is
     * already moving in that general direction, so a press that both stopped
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
     * The only way in for the page header's arrow buttons: `prev`/`next` are
     * the same `handleArrow` the old internal buttons called, so the
     * stop-before-paging rule above still applies no matter which markup is
     * driving it.
     */
    useImperativeHandle(ref, () => ({
        prev: () => handleArrow(-1),
        next: () => handleArrow(1),
    }));

    /*
     * Tells the caller's buttons when to grey out — see `ProductRailBounds`.
     * Read through a ref rather than a plain effect dependency: `onBoundsChange`
     * is a fresh function on every one of the caller's renders (it is not,
     * and does not need to be, wrapped in `useCallback`), and depending on it
     * directly would re-fire this effect, and re-notify the caller, on every
     * render of the page rather than only when the paging state itself
     * changes.
     */
    const onBoundsChangeRef = useRef(onBoundsChange);

    useEffect(() => {
        onBoundsChangeRef.current = onBoundsChange;
    });

    useEffect(() => {
        onBoundsChangeRef.current?.({ atStart, atEnd, autoplay });
    }, [atStart, atEnd, autoplay]);

    return (
        <div className="relativ">
            {/*
             * The row bleeds past the container on both sides and is faded
             * back out there, so the cards run off the edge of the page
             * instead of stopping dead in a line — the rail should look like
             * a slice of something longer, which is what the arrows promise.
             *
             * Scroll-snap only applies once autoplay has stopped: snapping
             * against a `scrollLeft` the hop loop is already driving fights
             * the browser's own snap-back and stutters.
             *
             * No `onWheel`/`onTouchStart`/`onPointerDown`/`onMouseEnter` here
             * on purpose — see the component doc. Only the arrows call
             * `stopAutoplay`, and nothing pauses.
             */}
            <ul
                ref={track}
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
                 * One copy while stopped; two back to back while autoplay is
                 * running — see the component doc for why. The second is
                 * `aria-hidden` and untabbable, same treatment the Gallery
                 * gives its own repeated copy just below on the page.
                 */}
                {(autoplay ? [0, 1] : [0]).map((copy) =>
                    columns.map((column, colIndex) => (
                        <li
                            key={`${copy}-${colIndex}`}
                            aria-hidden={copy === 1 || undefined}
                            /* Fixed widths rather than a fraction of the
                               track: a percentage basis would resize the
                               cards as the viewport grows, whereas a rail
                               should keep the card constant and simply show
                               more of them. A column, not a card: two stack
                               vertically inside it, which is what makes the
                               rail two rows deep. */
                            className="flex w-[15rem] shrink-0 snap-start flex-col gap-10 sm:w-[16.5rem]"
                        >
                            {column.map((product) => (
                                <RailCard
                                    key={product.id}
                                    product={product}
                                    currency={currency}
                                    tabIndex={copy === 1 ? -1 : undefined}
                                />
                            ))}
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
        </div>
    );
});

export default ProductRail;
