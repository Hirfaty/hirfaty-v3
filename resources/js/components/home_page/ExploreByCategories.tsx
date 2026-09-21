import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import Reveal from '@/components/home/Reveal';
import { WarmFill } from '@/components/WarmFill';
import useSnapCarousel from '@/hooks/use-snap-carousel';
import { useTranslation } from '@/hooks/use-translation';

type CategoryTile = {
    key: number;
    /** Admin-uploaded picture; null shows the placeholder. */
    image: string | null;
    href: string;
    label: string;
};

/** One tile: photograph (or placeholder), scrim, and label. */
function CategoryTileCard({ tile }: { tile: CategoryTile }) {
    return (
        <Link
            href={tile.href}
            className="group relative block aspect-[5/4] overflow-hidden rounded-md bg-souk-brown/5 lg:aspect-[16/11]"
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
            {/* Scrim only at the foot, where the
                        label sits — the rest of the
                        photograph stays true, since
                        nothing else on the tile needs to
                        read over it. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-souk-title/85 via-souk-title/25 to-transparent"
            />
            <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 p-5">
                <span className="font-display text-lg text-souk-cream">
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

export default function ExploreByCategories() {
    // The same top-level categories the header menu lists, shared from
    // HandleInertiaRequests — subcategories are not tiles.
    const { navCategories } = usePage<{
        navCategories?: Array<{
            id: number;
            translated_name: string;
            image_url: string | null;
        }>;
    }>().props;

    const { t, isRTL } = useTranslation();

    const categoryTiles: CategoryTile[] = (navCategories ?? []).map(
        (category) => ({
            key: category.id,
            image: category.image_url,
            href: `/products?category_id=${category.id}`,
            label: category.translated_name,
        }),
    );

    /*
     * Behind the wheel on narrow screens, where the categories become a
     * one-per-swipe carousel rather than a grid. Reuses the hero's rail logic
     * (paging tween, wrap-around, reduced-motion, display:none reset), and —
     * unlike the hero — never advances on its own, because these are
     * destinations, not something to watch. Hooks must run before the empty
     * check below, so this is called even when there are no tiles to show.
     */
    const { track, step, order, pauseProps } =
        useSnapCarousel<HTMLUListElement>(
            categoryTiles.length,
            Number.POSITIVE_INFINITY,
            isRTL,
        );

    if (categoryTiles.length === 0) {
        return null;
    }

    const arrow = `absolute top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 rotate-45 place-items-center rounded-sm bg-souk-paper/95 text-souk-brown shadow-lg transition-colors duration-300 hover:bg-souk-brown hover:text-souk-cream`;
    const chevron = `h-4.5 w-4.5 -rotate-45 rtl:rotate-[135deg]`;

    return (
        <section className="bg-souk-paper py-5 lg:py-4">
            <div className="container">
                {/* The grid these tiles form is only comfortable four-or-more
                    columns across. Below `sm` there is no room for it, so the
                    same links become a swipable rail — one category per
                    screen, arrows to page, a swipe to browse (see the hook
                    above). `sm:hidden` hides it where the grid takes over. */}
                <div {...pauseProps} className="relative sm:hidden">
                    <ul
                        ref={track}
                        className="flex snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        {order.map((tileIndex, position) => (
                            // Positional, because `order` repeats the first
                            // tile at the end — see `useSnapCarousel`.
                            <li
                                key={position}
                                className="relative w-full shrink-0 grow-0 basis-full snap-start snap-always"
                            >
                                <CategoryTileCard
                                    tile={categoryTiles[tileIndex]}
                                />
                            </li>
                        ))}
                    </ul>

                    {categoryTiles.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={() => step(-1)}
                                aria-label={t('welcome.categories_grid.prev')}
                                className={`${arrow} start-2`}
                            >
                                <ChevronLeft
                                    className={chevron}
                                    strokeWidth={1.5}
                                />
                            </button>
                            <button
                                type="button"
                                onClick={() => step(1)}
                                aria-label={t('welcome.categories_grid.next')}
                                className={`${arrow} end-2`}
                            >
                                <ChevronRight
                                    className={chevron}
                                    strokeWidth={1.5}
                                />
                            </button>
                        </>
                    )}
                </div>

                <div className="hidden gap-4 sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                    {categoryTiles.map((tile, i) => (
                        <Reveal key={tile.key} delay={200 + i * 100}>
                            <CategoryTileCard tile={tile} />
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
