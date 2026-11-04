import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import { WarmFill } from '@/components/WarmFill';
import { landingCategoryId } from '@/lib/category';

type CategoryTile = {
    key: number;
    /** Admin-uploaded picture; null shows the placeholder. */
    image: string | null;
    href: string;
    label: string;
};

/**
 * How many tiles the strip should hold before it is duplicated.
 *
 * The marquee works by rendering the set twice and sliding the track by half
 * its width (see the `marquee` keyframes in app.css), so the seam lands
 * exactly where the second copy begins. That only reads as continuous if one
 * copy is already wider than the viewport — with three categories it would
 * slide a short row into empty space and snap back. Repeating the set up to
 * this many tiles first guarantees enough width at any screen size.
 */
const MIN_TILES_PER_LAP = 8;

/**
 * One tile: photograph (or placeholder), scrim, and label.
 *
 * `decorative` marks a tile in the duplicated half of the marquee. Those are
 * there to make the wrap seamless and nothing else, so they are taken out of
 * the tab order — `aria-hidden` on the wrapper hides them from a screen
 * reader but leaves them perfectly tabbable, which would mean tabbing through
 * every category twice.
 */
function CategoryTileCard({
    tile,
    decorative = false,
}: {
    tile: CategoryTile;
    decorative?: boolean;
}) {
    return (
        <Link
            href={tile.href}
            tabIndex={decorative ? -1 : undefined}
            aria-hidden={decorative || undefined}
            /*
             * The spacing is a margin on the tile, not a `gap` on the track.
             * The marquee slides by exactly -50% of the track, so the two
             * halves must be the same width *including* the space between
             * them — with a container `gap` the seam lands half a gap out and
             * the loop visibly stutters once per lap. `me-` so it mirrors
             * under RTL.
             */
            className="group relative me-3 block aspect-[4/3] w-[56vw] shrink-0 overflow-hidden rounded-md bg-souk-brown/5 sm:me-4 sm:aspect-[5/4] sm:w-[40vw] lg:aspect-[16/11] lg:w-[22vw] xl:w-[18vw]"
        >
            {tile.image ? (
                <img
                    src={tile.image}
                    alt=""
                    loading="lazy"
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
            ) : (
                <WarmFill
                    variant="clay"
                    className="flex h-full w-full items-center justify-center transition-transform duration-500 group-hover:scale-105"
                >
                    <ZellijStarIcon
                        aria-hidden
                        className="h-10 w-10 text-souk-brown/25"
                    />
                </WarmFill>
            )}
            {/* Scrim only at the foot, where the label sits — the rest of the
                photograph stays true, since nothing else needs to read over
                it. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-souk-title/85 via-souk-title/25 to-transparent"
            />
            <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 p-3.5 sm:p-5">
                <span className="font-display text-base text-souk-cream sm:text-lg">
                    {tile.label}
                </span>
                <ArrowRight
                    className="h-4 w-4 shrink-0 text-souk-cream transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                    strokeWidth={1.75}
                />
            </div>
            <div
                aria-hidden="true"
                className="absolute inset-0 ring-1 ring-souk-brown/10 ring-inset"
            />
        </Link>
    );
}

/**
 * The category strip, as a marquee.
 *
 * Replaced a swipable rail below `sm` and a six-column grid above it. One
 * continuously drifting strip now serves both, which is why the carousel hook
 * and its arrows are gone: there is nothing to page through when the row
 * never stops.
 *
 * Full-bleed rather than inside the container — a marquee that starts and
 * ends at the text column reads as a row that happens to move, not as
 * something passing through.
 *
 * Three things keep it usable rather than merely decorative:
 *
 *   - it pauses on hover and on keyboard focus, because every tile is a link
 *     and a moving target is a hard one to click;
 *   - `prefers-reduced-motion` stops it outright, leaving a strip that can
 *     still be scrolled by hand — the same call `useSlideshow` makes for the
 *     hero;
 *   - the duplicate half is `aria-hidden`, so a screen reader hears each
 *     category once rather than twice.
 */
export default function ExploreByCategories() {
    // The same top-level categories the header menu lists, shared from
    // HandleInertiaRequests — subcategories are not tiles.
    const { navCategories } = usePage<{
        navCategories?: Array<{
            id: number;
            translated_name: string;
            image_url: string | null;
            children: Array<{ id: number }>;
        }>;
    }>().props;

    // Resolved at first render rather than in an effect: starting the
    // animation and then stopping it would already have moved the strip for
    // someone who asked for no motion.
    const [reduced] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    const categories: CategoryTile[] = (navCategories ?? []).map(
        (category) => ({
            key: category.id,
            image: category.image_url,
            href: route('products.index', {
                category_id: landingCategoryId(category),
            }),
            label: category.translated_name,
        }),
    );

    if (categories.length === 0) {
        return null;
    }

    // One lap of the marquee — the set repeated until it is wide enough to
    // cover the screen on its own. See MIN_TILES_PER_LAP.
    const lap: CategoryTile[] = [];

    while (lap.length < MIN_TILES_PER_LAP) {
        lap.push(...categories);
    }

    return (
        <section className="overflow-hidden bg-souk-paper py-4 sm:py-5 lg:py-4">
            {/*
             * `group` so hover anywhere on the strip pauses it, not just over
             * the one tile the cursor is on — otherwise the row slides out
             * from under a cursor that is clearly aiming at something.
             */}
            <div className="group relative">
                <div
                    className={`flex w-max ${
                        reduced
                            ? // Nothing moves, but the strip is still a strip:
                              // it stays scrollable by hand rather than
                              // clipping the categories that fall off-screen.
                              'max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden'
                            : 'animate-gallery group-focus-within:[animation-play-state:paused] group-hover:[animation-play-state:paused] rtl:animate-gallery-rtl'
                    }`}
                >
                    {lap.map((tile, i) => (
                        <CategoryTileCard key={`a-${i}`} tile={tile} />
                    ))}

                    {/*
                     * The second copy exists only so the slide can wrap
                     * seamlessly; it carries no information a reader has not
                     * already had, and is skipped for screen readers and for
                     * tabbing. Dropped entirely under reduced motion, where
                     * there is no wrap to hide and it would just be a
                     * duplicated row to scroll past.
                     */}
                    {!reduced && (
                        <div
                            aria-hidden="true"
                            className="pointer-events-none flex"
                        >
                            {lap.map((tile, i) => (
                                <CategoryTileCard
                                    key={`b-${i}`}
                                    tile={tile}
                                    decorative
                                />
                            ))}
                        </div>
                    )}
                </div>

                {/*
                 * Soft edges, so tiles arrive and leave rather than being cut
                 * off at the viewport. Non-interactive, and keyed to the page
                 * colour so it works on either side under RTL.
                 */}
                {!reduced && (
                    <>
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-y-0 left-0 w-12 bg-gradient-to-r from-souk-paper to-transparent lg:w-24"
                        />
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-y-0 right-0 w-12 bg-gradient-to-l from-souk-paper to-transparent lg:w-24"
                        />
                    </>
                )}
            </div>
        </section>
    );
}
