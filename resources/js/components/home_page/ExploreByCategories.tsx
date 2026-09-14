import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import { useSnapCarousel } from '@/hooks/use-snap-carousel';
import { useTranslation } from '@/hooks/use-translation';
import exploreArt from '@/images/categories/art.jpeg';
import exploreLantern from '@/images/categories/lantern.jpeg';
import exploreLeather from '@/images/categories/lether.jpeg';
import explorePottery from '@/images/categories/pottery.jpeg';
import exploreTextile from '@/images/categories/textile.jpeg';
import exploreWood from '@/images/categories/wood.jpeg';

const EXPLORE_CATEGORIES = [
    { key: 'pottery', image: explorePottery, slugMatch: 'poterie' },
    { key: 'textile', image: exploreTextile, slugMatch: 'textile' },
    { key: 'art', image: exploreArt, slugMatch: 'peinture' },
    { key: 'lantern', image: exploreLantern, slugMatch: 'lanterne' },
    { key: 'wood', image: exploreWood, slugMatch: 'bois' },
    { key: 'leather', image: exploreLeather, slugMatch: 'leather' },
] as const;

type CategoryTile = {
    key: string;
    image: string;
    href: string;
    label: string;
};

/**
 * One tile's contents, shared between the grid (`sm` and up) and the mobile
 * carousel — same photograph, scrim, and label, just wrapped by a different
 * layout container.
 */
function CategoryTileCard({ tile }: { tile: CategoryTile }) {
    return (
        <Link
            href={tile.href}
            className="group relative block aspect-[4/5] overflow-hidden rounded-xl bg-souk-brown/5"
        >
            <img
                src={tile.image}
                alt=""
                loading="lazy"
                className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
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

/**
 * Phone-only rail: one tile at a time, swipable and auto-advancing, the same
 * scroll-snap behaviour `HeroSlider` uses for the hero photographs. Replaces
 * the grid below `sm`, where six tiles across the width would otherwise be
 * squeezed into a scrollable but static two-column block.
 */
function CategoryCarousel({
    tiles,
    isRTL,
    label,
}: {
    tiles: CategoryTile[];
    isRTL: boolean;
    label: string;
}) {
    const { track, index, goTo, pauseProps } =
        useSnapCarousel<HTMLUListElement>(tiles.length, 4000, isRTL);

    return (
        <div {...pauseProps} className="relative mt-10 sm:hidden">
            <ul
                ref={track}
                className="flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {tiles.map((tile) => (
                    <li
                        key={tile.key}
                        className="w-full shrink-0 grow-0 basis-full snap-start snap-always"
                    >
                        <CategoryTileCard tile={tile} />
                    </li>
                ))}
            </ul>

            {tiles.length > 1 && (
                <div
                    role="group"
                    aria-label={label}
                    className="mt-5 flex items-center justify-center gap-2.5"
                >
                    {tiles.map((tile, i) => (
                        <button
                            key={tile.key}
                            type="button"
                            onClick={() => goTo(i)}
                            aria-label={tile.label}
                            aria-current={i === index}
                            className={`h-1.5 rounded-full transition-all duration-500 ${
                                i === index
                                    ? 'w-8 bg-souk-brown'
                                    : 'w-1.5 bg-souk-brown/25 hover:bg-souk-brown/50'
                            }`}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function ExploreByCategories() {
    const { t, isRTL } = useTranslation();

    const { navCategories } = usePage<{
        navCategories?: Array<{
            id: number;
            slug: string;
            translated_name: string;
        }>;
    }>().props;

    const categoryTiles = EXPLORE_CATEGORIES.map((tile) => {
        const category = navCategories?.find((c) =>
            c.slug.includes(tile.slugMatch),
        );

        return {
            ...tile,
            href: category
                ? `/products?category_id=${category.id}`
                : '/products',
            label:
                category?.translated_name ??
                t(`welcome.categories_grid.${tile.key}`),
        };
    });

    return (
        <section className="bg-souk-paper py-8 lg:py-8">
            <div className="container">
                <Reveal delay={140}>
                    <h2 className="mt-4 text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                        {t('welcome.categories_grid.heading')}
                    </h2>
                </Reveal>

                <CategoryCarousel
                    tiles={categoryTiles}
                    isRTL={isRTL}
                    label={t('welcome.categories_grid.heading')}
                />

                <div className="mt-14 hidden gap-6 sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
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
