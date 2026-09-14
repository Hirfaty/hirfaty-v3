import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import type { RefObject } from 'react';
import { useState } from 'react';
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

/** How many products fill the grid: 4 columns by 2 rows. */
const GRID_SIZE = 8;

/**
 * The arch shape SiteHeader's own icon buttons use (rounded on top, squared at
 * the foot), so this pair reads as belonging to the same button vocabulary
 * rather than as a one-off control invented for this section.
 */
const arrowButton =
    'grid h-9 w-9 cursor-pointer shrink-0 place-items-center rounded-t-full rounded-b-md text-souk-brown transition-colors hover:bg-souk-brown hover:text-souk-cream';

/** The pair above the grid, turning its pages, drawn on the page colour. */
const gridArrow = `${arrowButton} ring-1 ring-souk-brown/25 disabled:pointer-events-none disabled:opacity-30`;

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
function ShowcaseCard({
    product,
    currency,
}: {
    product: ShowcaseProduct;
    currency: string;
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
            className="group flex min-h-0 flex-col"
        >
            {/*
             * Grows into whatever height the row has left over from `lg` up
             * (the row is a screenful there, so the photograph is the part
             * that flexes and the caption below keeps its natural size);
             * falls back to a fixed aspect on a phone, where the grid is free
             * to be as tall as its contents.
             */}
            <div className="relative aspect-[4/5] shrink-0 overflow-hidden rounded-sm bg-souk-brown/5 lg:aspect-auto lg:min-h-0 lg:flex-1">
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
            </div>
        </Link>
    );
}

/**
 * The home page's "Explore our products" body: a swipable spotlight down the
 * leading third, a fixed 4 x 2 grid of eight products filling the rest.
 *
 * Two sets of controls, each belonging to one half. The grid's arrows turn its
 * pages and live at the top of the section next to the "view all" link; the
 * spotlight's sit in the corner of its own photograph. What is gone is the
 * "x / y" counter that used to accompany the grid's pair.
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
    currency,
    labels,
    isRTL,
}: {
    products: ShowcaseProduct[];
    /** Already-translated currency suffix, e.g. "MAD". */
    currency: string;
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

    const [page, setPage] = useState(0);

    const pageCount = Math.max(1, Math.ceil(gridProducts.length / GRID_SIZE));
    /*
     * Clamped on read rather than trusted: `products` comes from the server and
     * can be shorter on a later visit than it was when `page` was last set,
     * which would otherwise leave the grid on an empty page.
     */
    const current = Math.min(page, pageCount - 1);
    const visible = gridProducts.slice(
        current * GRID_SIZE,
        current * GRID_SIZE + GRID_SIZE,
    );

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

                {pageCount > 1 && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setPage(Math.max(0, current - 1))}
                            disabled={current === 0}
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
                            onClick={() =>
                                setPage(Math.min(pageCount - 1, current + 1))
                            }
                            disabled={current >= pageCount - 1}
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

            <div className="flex flex-col gap-8 lg:h-[100svh] lg:max-h-[900px] lg:min-h-[620px] lg:flex-row lg:gap-10">
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

                <div
                    key={current}
                    className="grid min-h-0 flex-1 animate-in grid-cols-2 gap-x-5 gap-y-6 duration-500 fade-in lg:grid-cols-4 lg:grid-rows-2"
                >
                    {visible.map((product) => (
                        <ShowcaseCard
                            key={product.id}
                            product={product}
                            currency={currency}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
