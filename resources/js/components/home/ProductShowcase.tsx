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

/** How many products fill the grid: 4 columns by 2 rows. */
const GRID_SIZE = 8;

/**
 * The arch shape SiteHeader's own icon buttons use (rounded on top, squared at
 * the foot), so this pair reads as belonging to the same button vocabulary
 * rather than as a one-off control invented for this section.
 */
const arrowButton =
    'grid h-9 w-9 cursor-pointer shrink-0 place-items-center rounded-t-full rounded-b-md text-souk-brown ring-1 ring-souk-brown/25 transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:pointer-events-none disabled:opacity-30';

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
    goTo,
    pauseProps,
}: {
    /** Only products with photography — see `ProductShowcase` for the filter. */
    frames: ShowcaseProduct[];
    track: RefObject<HTMLUListElement | null>;
    index: number;
    goTo: (index: number) => void;
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
     * (the section is as tall as its content), so the panel has to derive its
     * own; side by side on a laptop the section is a fixed height and the panel
     * must fill whatever the grid beside it is given, whatever shape that is.
     */
    const shell =
        'relative isolate aspect-[4/5] overflow-hidden rounded-xl bg-souk-brown/5 lg:aspect-auto lg:h-full';

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
                {frames.map((product) => {
                    const image = (product.gallery_images ?? product.images)[0];

                    return (
                        <li
                            key={product.id}
                            className="relative h-full w-full shrink-0 grow-0 basis-full snap-start snap-always"
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

            <Link
                href={`/products/${frames[index].slug}`}
                className="absolute inset-x-0 bottom-0 z-30 flex flex-col justify-end p-6"
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
                <div className="absolute inset-x-0 bottom-24 z-30 flex items-center justify-center gap-2">
                    {frames.map((product, i) => (
                        <button
                            key={product.id}
                            type="button"
                            onClick={() => goTo(i)}
                            aria-label={product.name}
                            aria-current={i === index}
                            className={`h-1.5 rounded-full transition-all duration-500 ${
                                i === index
                                    ? 'w-8 bg-souk-cream'
                                    : 'w-1.5 bg-white/45 hover:bg-white/75'
                            }`}
                        />
                    ))}
                </div>
            )}

            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-40 rounded-xl ring-1 ring-souk-brown/10 ring-inset"
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
             * (the section is a fixed height there, so the photograph is the
             * part that flexes and the caption below keeps its natural size);
             * falls back to a fixed aspect on a phone, where the grid is free
             * to be as tall as its contents.
             */}
            <div className="relative aspect-[4/5] shrink-0 overflow-hidden rounded-xl bg-souk-brown/5 lg:aspect-auto lg:min-h-0 lg:flex-1">
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
                    className="absolute inset-0 rounded-xl ring-1 ring-souk-brown/10 ring-inset"
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
 * The controls that used to page the grid (an arrow pair plus a "x / y"
 * counter, sat above the grid) are gone — the grid no longer pages, it simply
 * shows its eight products. What is left of them, the arrow pair and the
 * "view all" link, now sits at the very top of the whole section rather than
 * above just the grid, and the arrows drive the spotlight carousel instead.
 *
 * The whole section is sized to fit a laptop screen without scrolling, which is
 * why the heights here are driven top-down rather than by each card's own
 * aspect ratio: the caller gives this component a bounded height, the grid
 * splits it into two rows, and each card's photograph takes whatever the
 * caption underneath it does not. Sizing from aspect ratios instead — the
 * obvious way to build it — makes the section's height a function of the
 * container's *width*, and at 70% of a wide container two 4:5 rows come out
 * around 1000px tall, well past the fold on the laptops this is designed for.
 *
 * Below `lg` the two panels stack and every box falls back to a fixed aspect
 * ratio: there is no "fits on screen" to honour on a phone, and forcing one
 * would only shrink the photography to stamps.
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
    labels: { prev: string; next: string; viewAll: string };
    isRTL: boolean;
}) {
    const spotlightProducts = products.slice(0, SPOTLIGHT_SIZE);
    const gridProducts = products.slice(
        SPOTLIGHT_SIZE,
        SPOTLIGHT_SIZE + GRID_SIZE,
    );

    /*
     * Only products that actually have photography: a frame with nothing in it
     * would leave a blank pane in the middle of the swipe sequence.
     */
    const frames = spotlightProducts.filter(
        (product) => (product.gallery_images ?? product.images).length > 0,
    );

    const { track, index, goTo, step, pauseProps } =
        useSnapCarousel<HTMLUListElement>(frames.length, 4500, isRTL);

    return (
        <div className="flex h-full flex-col gap-6 lg:gap-8">
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

                {frames.length > 1 && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            aria-label={labels.prev}
                            className={arrowButton}
                        >
                            <ChevronLeft
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            aria-label={labels.next}
                            className={arrowButton}
                        >
                            <ChevronRight
                                className="h-4 w-4 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        </button>
                    </div>
                )}
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-8 lg:flex-row lg:gap-10">
                {/*
                 * 30% of the row, and not allowed to shrink below it — the grid
                 * beside it is the flexible half. A basis rather than a width so
                 * the `gap` comes out of the two panels proportionally instead of
                 * pushing the total past 100%.
                 */}
                <div className="lg:min-h-0 lg:w-[30%] lg:shrink-0">
                    <Spotlight
                        frames={frames}
                        track={track}
                        index={index}
                        goTo={goTo}
                        pauseProps={pauseProps}
                    />
                </div>

                <div className="grid min-h-0 flex-1 grid-cols-4 gap-x-5 gap-y-6 lg:grid-rows-2">
                    {gridProducts.map((product) => (
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
