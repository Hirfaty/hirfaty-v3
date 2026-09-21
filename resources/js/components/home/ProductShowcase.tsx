import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import type { RefObject } from 'react';
import VaseIcon from '@/components/home/VaseIcon';
import { useHoverGallery } from '@/hooks/use-hover-gallery';
import { useSnapCarousel } from '@/hooks/use-snap-carousel';

export type ShowcaseProduct = {
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
     * backend eager-loaded variant media (the home page does). Falls back to
     * `images` below when absent, so a product with no variants (or fetched
     * through a path that skips the eager load) still shows its own photos.
     */
    gallery_images?: { id: number; url: string; type: string }[];
};

/** How many products fill the spotlight carousel down the leading third. */
const SPOTLIGHT_SIZE = 5;

/** How many rows the grid is split into, each its own rail. */
const BAND_ROWS = 2;

/**
 * One slide of a row's rail: 4 products.
 *
 * Laid out 1 x 4 from `lg` up and 2 x 2 below it, so a slide is always the same
 * four products whatever the breakpoint and the rails never have to re-band
 * themselves on resize.
 */
export const BAND_SIZE = 4;

/** How many products fill the grid: 4 columns by 2 rows. */
const GRID_SIZE = BAND_SIZE * BAND_ROWS;

/**
 * How long a row holds before it slides on.
 *
 * Deliberately not the spotlight's 4500ms: three rails on one beat would
 * periodically fire together, which reads as the whole section lurching rather
 * than as three things quietly taking their turns.
 */
const BAND_INTERVAL = 5200;

/**
 * The arch shape SiteHeader's own icon buttons use (rounded on top, squared at
 * the foot), so this pair reads as belonging to the same button vocabulary
 * rather than as a one-off control invented for this section.
 */
const arrowButton =
    'grid h-9 w-9 cursor-pointer shrink-0 place-items-center rounded-t-full rounded-b-md text-souk-brown transition-colors hover:bg-souk-brown hover:text-souk-cream';

/**
 * The pair above the grid, turning its pages, drawn on the page colour. No
 * `disabled:` styling: the grid wraps, so neither arrow is ever dead.
 */
const gridArrow = `${arrowButton} ring-1 ring-souk-brown/25`;

/**
 * The spotlight's own pair, sitting on the photograph rather than on the page,
 * so they need their own ground to read against — hence the paper fill, where
 * the grid's get away with a hairline ring. The arch shape, borrowed from
 * SiteHeader's icon buttons, is the same either way, so the two pairs read as
 * the same kind of control doing the same kind of job to different things.
 */
const spotlightArrow = `${arrowButton} bg-souk-paper/85 backdrop-blur-sm`;

/**
 * The tall panel down the leading third of the section: swipable and
 * auto-advancing, the same scroll-snap behaviour `HeroSlider` gives the hero
 * photographs, so the two full-bleed rails on the page move the same way.
 *
 * No price, by design — this is the section's poster, not a card. A shopper
 * reading a price has already decided to compare, and comparing is what the
 * grid beside it is for; the spotlight only has to be worth looking at long
 * enough to get someone into the grid. Each frame is its own link, pointing at
 * the product currently on screen.
 */
function Spotlight({
    frames,
    track,
    index,
    step,
    order,
    labels,
    pauseProps,
}: {
    /** Only products with photography — see `ProductShowcase` for the filter. */
    frames: ShowcaseProduct[];
    track: RefObject<HTMLUListElement | null>;
    index: number;
    step: (delta: 1 | -1) => void;
    /** Render order including the wrap-around clones — see `useSnapCarousel`. */
    order: number[];
    labels: { spotlightPrev: string; spotlightNext: string };
    pauseProps: {
        onMouseEnter: () => void;
        onMouseLeave: () => void;
        onFocusCapture: () => void;
        onBlurCapture: () => void;
    };
}) {
    /*
     * `aspect-[4/5]` below `lg`, `h-full` from `lg` up — the same switch every
     * box in this section makes. Stacked on a phone there is no height to fill
     * (the row is as tall as its content), so the panel has to derive its own;
     * side by side on a laptop the row is a screenful and the panel must fill
     * whatever the grid beside it is given, whatever shape that is.
     */
    const shell =
        'relative isolate aspect-[4/5] overflow-hidden rounded-md bg-souk-brown/5 lg:aspect-auto lg:h-full';

    if (frames.length === 0) {
        return (
            <div className={`${shell} grid place-items-center`}>
                <VaseIcon className="h-16 w-16 text-souk-brown/25" />
            </div>
        );
    }

    return (
        <div {...pauseProps} className={shell}>
            <ul
                ref={track}
                className="flex h-full snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {order.map((frame, position) => {
                    const product = frames[frame];
                    const image = (product.gallery_images ?? product.images)[0];

                    return (
                        <li
                            // Positional, because `order` repeats a frame at
                            // each end — see `useSnapCarousel`.
                            key={position}
                            className="relative h-full w-full shrink-0 grow-0 basis-full snap-start snap-always rounded-sm"
                        >
                            <img
                                src={image.url}
                                alt=""
                                loading="lazy"
                                className="h-full w-full object-cover"
                                draggable={false}
                            />
                        </li>
                    );
                })}
            </ul>

            {/*
             * Scrim across the foot only, where the caption sits — the rest of
             * the photograph stays true, the same treatment the category tiles
             * above give theirs.
             */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-2/3 bg-gradient-to-t from-souk-title/85 via-souk-title/25 to-transparent"
            />

            {/*
             * Caption and controls share one row along the foot rather than the
             * caption being the whole strip: the arrows have to sit on top of
             * the photograph, and a link spanning the full width underneath
             * them would swallow the clicks meant for them.
             */}
            <div className="absolute inset-x-0 bottom-0 z-30 flex items-end justify-between gap-4 p-6">
                <Link
                    href={`/products/${frames[index].slug}`}
                    className="flex min-w-0 flex-1 flex-col"
                >
                    <span className="font-display text-xl leading-snug text-souk-cream">
                        {frames[index].name}
                    </span>
                    {frames[index].store.store_name && (
                        <span className="mt-1 line-clamp-1 text-xs text-souk-cream/70">
                            {frames[index].store.store_name}
                        </span>
                    )}
                </Link>

                {frames.length > 1 && (
                    <div className="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            aria-label={labels.spotlightPrev}
                            className={spotlightArrow}
                        >
                            <ChevronLeft
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            aria-label={labels.spotlightNext}
                            className={spotlightArrow}
                        >
                            <ChevronRight
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                    </div>
                )}
            </div>

            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-40 rounded-md ring-1 ring-souk-brown/10 ring-inset"
            />
        </div>
    );
}

/**
 * One card in the grid.
 *
 * Split out from the `.map()` below rather than left inline: `useHoverGallery`
 * is a hook, and hooks may only run at the top level of a component — calling
 * one from inside a loop callback is exactly the "different hook order between
 * renders" case the Rules of Hooks exist to catch, since it would run once per
 * item rather than being tied to one.
 */
export function ShowcaseCard({
    product,
    money,
    fill = true,
}: {
    product: ShowcaseProduct;
    /**
     * Formats a MAD figure in whatever currency the visitor is reading, the
     * currency label included. Passed in rather than read from
     * `useCurrency` here so this stays a dumb component — the same contract
     * `labels` and `isRTL` already work to.
     */
    money: (value: number | string) => string;
    /**
     * Whether the photograph should stretch to fill the height its row was
     * given, rather than keep its own 4:5 shape.
     *
     * True inside the showcase, where the row is a fixed screenful and the
     * cards divide it up. False anywhere the row has no height of its own —
     * `NewArrivals`, for one — because `flex-1` against an auto-height parent
     * resolves to nothing and would collapse the photograph to a hairline.
     */
    fill?: boolean;
}) {
    const gallery = product.gallery_images ?? product.images;
    const { index, previous, hoverProps } = useHoverGallery(gallery.length);

    const base =
        product.base_price !== null ? Number(product.base_price) : null;
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = base !== null && sale !== null && sale < base;

    return (
        /*
         * No entrance animation: the card does not appear, it travels. Its row
         * is a scroll-snap rail that slides the whole band into view, so a fade
         * on top of that would only make the arriving products hazy while they
         * moved.
         */
        <Link
            {...hoverProps}
            href={`/products/${product.slug}`}
            className="group flex min-h-0 flex-col"
        >
            {/*
             * Grows into whatever height the row has left over from `lg` up
             * (the row is a screenful there, so the photograph is the part
             * that flexes and the caption below keeps its natural size);
             * falls back to a fixed aspect on a phone, where the grid is free
             * to be as tall as its contents — and everywhere, when the row it
             * sits in has no height of its own to divide up.
             */}
            <div
                className={`relative aspect-[4/5] shrink-0 overflow-hidden rounded-sm bg-souk-brown/5 ${
                    fill ? 'lg:aspect-auto lg:min-h-0 lg:flex-1' : ''
                }`}
            >
                {gallery.length > 0 ? (
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
                    className="absolute inset-0 rounded-md ring-1 ring-souk-brown/10 ring-inset"
                />
            </div>

            <div className="mt-3 shrink-0">
                <h3 className="line-clamp-1 font-display text-base leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                    {product.name}
                </h3>
                <div className="mt-1 flex items-baseline gap-2">
                    {base !== null ? (
                        <>
                            <span className="font-display text-[15px] text-souk-brown">
                                {money(onSale ? sale : base)}{' '}
                                <span className="text-[10px]">TTC</span>
                            </span>
                            {onSale && (
                                <span className="text-xs text-souk-brown-deep/45 line-through">
                                    {money(base)}
                                </span>
                            )}
                        </>
                    ) : (
                        <span className="text-xs text-souk-brown-deep/45">
                            —
                        </span>
                    )}
                </div>
            </div>
        </Link>
    );
}

/**
 * One row of the product grid, as a swipable rail.
 *
 * Built the same way as `Spotlight` and the hero: a scroll-snap track whose
 * slides are each a full width of the row, tweened between by `useSnapCarousel`
 * and swipable by hand in between. The only difference is what a slide holds —
 * four product cards rather than one photograph.
 *
 * `offset` is what keeps the two rows showing different products: row r starts
 * its sequence at band `r × BAND_ROWS` and both then walk forward through the
 * same bands at the same rate, half a beat apart.
 *
 * Why `r × BAND_ROWS` and not simply `r`. Running half a beat apart means the
 * rows' own indices are either equal (both have ticked) or one apart (the top
 * has ticked and the bottom has not yet). Row 1's band is `offset + i₁` and row
 * 0's is `i₀`, so the two collide whenever `offset ≡ i₀ - i₁` — that is,
 * whenever `offset` is 0 or 1. An offset of 1 therefore looks right and is
 * wrong exactly half the time: every time the top row ticked, both rows showed
 * the same four products until the bottom row caught up. Two is the smallest
 * offset that clears both phases.
 */
export function GridRail({
    products,
    bandCount,
    offset,
    slides,
    track,
    order,
    money,
    fill = true,
    bandSize = BAND_SIZE,
    columnsClassName = 'grid-cols-2 lg:grid-cols-4',
}: {
    /** The whole grid pool; this rail slices its own bands out of it. */
    products: ShowcaseProduct[];
    bandCount: number;
    offset: number;
    /** How many slides this rail has — see `ProductShowcase`. */
    slides: number;
    track: RefObject<HTMLUListElement | null>;
    /** Render order including the wrap-around clone — see `useSnapCarousel`. */
    order: number[];
    money: (value: number | string) => string;
    /**
     * Whether the rail fills the height it was given rather than taking its
     * own from its cards — see `ShowcaseCard`. False for a row standing on its
     * own, like `NewArrivals`.
     */
    fill?: boolean;
    /**
     * How many products make up one slide of this rail. Defaults to the
     * showcase's own four; `NewArrivals` overrides it to six, which is why
     * this is not simply the module constant baked in — a caller with a
     * different slide width also owns `bandCount`, which is computed against
     * that same width, so the two never disagree about where one slide ends.
     */
    bandSize?: number;
    /**
     * The grid's own column classes, since a slide of six needs a different
     * split than a slide of four. Kept as one Tailwind string rather than a
     * column *count* prop — grid-cols-N is only ever meaningful in whole
     * literal classes the compiler can see statically, so a number here would
     * have to be turned back into one of these strings anyway.
     */
    columnsClassName?: string;
}) {
    return (
        <ul
            ref={track}
            className={`flex snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden ${
                fill ? 'lg:h-full' : ''
            }`}
        >
            {order.map((slide, position) => {
                const band = (offset + slide) % Math.max(slides, 1);
                const start = (band % Math.max(bandCount, 1)) * bandSize;

                return (
                    <li
                        // Positional, because `order` repeats a slide at the
                        // end — see `useSnapCarousel`.
                        key={position}
                        className={`grid w-full shrink-0 grow-0 basis-full snap-start snap-always gap-x-5 gap-y-6 ${columnsClassName} ${
                            fill ? 'lg:h-full' : ''
                        }`}
                    >
                        {products
                            .slice(start, start + bandSize)
                            .map((product) => (
                                <ShowcaseCard
                                    key={product.id}
                                    product={product}
                                    money={money}
                                    fill={fill}
                                />
                            ))}
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * The home page's "Explore our products" body: a swipable spotlight down the
 * leading third, a 4 x 2 grid of eight products filling the rest.
 *
 * Both halves move by themselves. The spotlight swaps its whole photograph; the
 * grid turns over one *row* at a time — the top four, then on the next beat the
 * bottom four — so the section always has something quietly in motion without
 * ever replacing everything at once, which at this size reads as the page
 * jumping. See `useStaggeredBands` for the alternation, and `BAND_INTERVAL` for
 * why the two halves deliberately run on different clocks.
 *
 * Two sets of controls, each belonging to one half. The grid's arrows turn it a
 * full page at a time and live at the top of the section next to the "view all"
 * link; the spotlight's sit in the corner of its own photograph. Neither pair
 * disables at an end any more, because neither half has one — both wrap.
 *
 * From `lg` up the row holding those two panels is a full viewport tall, and
 * the heading and controls above it add to that rather than come out of it —
 * the screenful is meant to be the merchandise, not the furniture around it.
 * That height is then driven top-down rather than by each card's own aspect
 * ratio: the row fixes the height, the grid splits it into two rows, and each
 * card's photograph takes whatever the caption underneath it does not. Sizing
 * from aspect ratios instead — the obvious way to build it — would make the
 * height a function of the container's *width*, and at 70% of a wide container
 * two 4:5 rows come out around 1000px tall.
 *
 * Below `lg` the two panels stack, the screenful is given up, and every box
 * falls back to a fixed aspect ratio: there is no "fits on screen" worth
 * honouring on a phone, and forcing one would only shrink the photography to
 * stamps. The grid drops to two columns there for the same reason.
 */
export default function ProductShowcase({
    products,
    money,
    labels,
    isRTL,
}: {
    products: ShowcaseProduct[];
    /** Formats a MAD figure in the visitor's currency — see ShowcaseCard. */
    money: (value: number | string) => string;
    /**
     * Passed in rather than read from `useTranslation` so this stays a dumb
     * component — the same contract `HeroSlider` next door works to.
     */
    labels: {
        prev: string;
        next: string;
        spotlightPrev: string;
        spotlightNext: string;
        viewAll: string;
    };
    isRTL: boolean;
}) {
    const spotlightProducts = products.slice(0, SPOTLIGHT_SIZE);
    const gridProducts = products.slice(SPOTLIGHT_SIZE);

    /*
     * Only products that actually have photography: a frame with nothing in it
     * would leave a blank pane in the middle of the swipe sequence.
     */
    const frames = spotlightProducts.filter(
        (product) => (product.gallery_images ?? product.images).length > 0,
    );

    const { track, index, step, order, pauseProps } =
        useSnapCarousel<HTMLUListElement>(frames.length, 4500, isRTL);

    /*
     * Floored, not rounded up: a trailing band of one or two products would
     * turn over into a row with empty cells in it. Dropping the remainder
     * costs at most three products that were never going to be on screen at
     * the same time anyway, and keeps every row of the grid full.
     */
    const bandCount = Math.floor(gridProducts.length / BAND_SIZE);

    /*
     * Three bands is the floor for rolling the rows. With two, both are on
     * screen already and any advance would land one row on the band the other
     * is showing — the same four products twice over. A pool that short is laid
     * out flat and simply does not move, which is what the grid did before it
     * rolled at all.
     */
    const rotating = bandCount > BAND_ROWS;
    const slides = rotating ? bandCount : 1;

    /*
     * One rail per row, on the same interval but half a beat apart, which is
     * what makes them take turns: the top row slides, then the bottom, then the
     * top again. Two calls rather than a loop because hooks may not run inside
     * one — and because the arrows below need to reach both.
     */
    const top = useSnapCarousel<HTMLUListElement>(slides, BAND_INTERVAL, isRTL);
    const bottom = useSnapCarousel<HTMLUListElement>(
        slides,
        BAND_INTERVAL,
        isRTL,
        BAND_INTERVAL / 2,
    );

    /*
     * Hovering or tabbing anywhere in the grid holds *both* rows. Pausing only
     * the row under the cursor would leave the other one moving under the
     * reader's eye, which is the thing the pause exists to prevent.
     */
    const gridPause = {
        onMouseEnter: () => {
            top.pauseProps.onMouseEnter();
            bottom.pauseProps.onMouseEnter();
        },
        onMouseLeave: () => {
            top.pauseProps.onMouseLeave();
            bottom.pauseProps.onMouseLeave();
        },
        onFocusCapture: () => {
            top.pauseProps.onFocusCapture();
            bottom.pauseProps.onFocusCapture();
        },
        onBlurCapture: () => {
            top.pauseProps.onBlurCapture();
            bottom.pauseProps.onBlurCapture();
        },
    };

    /**
     * Both rows slide one band, in the direction asked for — the same move the
     * spotlight's own arrows make, so the two halves of the section answer a
     * click the same way.
     *
     * One band rather than a whole page of two. Stepping by two looks like it
     * should turn the grid over in one go, but the rails wrap: with three bands
     * in the pool, +2 from band 1 lands on band 0, and a "next" click visibly
     * slides the row *backwards*. Moving both rows by the same single band also
     * preserves the gap between them, so the pair still cannot collide.
     */
    const turnPage = (delta: 1 | -1) => {
        top.step(delta);
        bottom.step(delta);
    };

    return (
        <div className="flex flex-col gap-6 lg:gap-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/products"
                    className="group inline-flex items-center gap-2 border-b border-souk-brown/40 pb-1 text-sm text-souk-brown transition-colors hover:border-souk-brown"
                >
                    {labels.viewAll}
                    <ArrowRight
                        className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                        strokeWidth={1.75}
                    />
                </Link>

                {/*
                 * No longer disabled at the ends, because there are no ends:
                 * the grid wraps now that it also turns itself, exactly as the
                 * spotlight's own pair has always done.
                 */}
                {rotating && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => turnPage(-1)}
                            aria-label={labels.prev}
                            className={gridArrow}
                        >
                            <ChevronLeft
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => turnPage(1)}
                            aria-label={labels.next}
                            className={gridArrow}
                        >
                            <ChevronRight
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-8 lg:h-[85svh] lg:max-h-[900px] lg:min-h-[620px] lg:flex-row lg:gap-10">
                <div className="lg:min-h-0 lg:w-[30%] lg:shrink-0">
                    <Spotlight
                        frames={frames}
                        track={track}
                        index={index}
                        step={step}
                        order={order}
                        labels={labels}
                        pauseProps={pauseProps}
                    />
                </div>

                {/*
                 * `gridPause` on the wrapper rather than on each rail: the whole
                 * grid is what should hold still while someone is reading it or
                 * tabbing through it, not just the row under the cursor.
                 */}
                <div
                    {...gridPause}
                    className="flex min-h-0 flex-1 flex-col gap-y-6"
                >
                    {rotating ? (
                        [top, bottom].map((rail, row) => (
                            <div
                                key={row}
                                className="min-w-0 lg:min-h-0 lg:flex-1"
                            >
                                <GridRail
                                    products={gridProducts}
                                    bandCount={bandCount}
                                    offset={row * BAND_ROWS}
                                    slides={slides}
                                    track={rail.track}
                                    order={rail.order}
                                    money={money}
                                />
                            </div>
                        ))
                    ) : (
                        /*
                         * Too few products to roll — laid out as a plain grid
                         * rather than as two rails that could never move.
                         */
                        <div className="grid min-h-0 flex-1 grid-cols-2 gap-x-5 gap-y-6 lg:grid-cols-4 lg:grid-rows-2">
                            {gridProducts.slice(0, GRID_SIZE).map((product) => (
                                <ShowcaseCard
                                    key={product.id}
                                    product={product}
                                    money={money}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
