import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import Reveal from '@/components/home/Reveal';
import { WarmFill } from '@/components/WarmFill';

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

    const categoryTiles: CategoryTile[] = (navCategories ?? []).map(
        (category) => ({
            key: category.id,
            image: category.image_url,
            href: `/products?category_id=${category.id}`,
            label: category.translated_name,
        }),
    );

    if (categoryTiles.length === 0) {
        return null;
    }

    return (
        <section className="hidden bg-souk-paper py-5 sm:block lg:py-4">
            <div className="container">
                {/* <Reveal delay={140}>
                    <h2 className="text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                        {t('welcome.categories_grid.heading')}
                    </h2>
                </Reveal> */}

                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
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
