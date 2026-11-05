import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import { WarmFill } from '@/components/WarmFill';
import { useTranslation } from '@/hooks/use-translation';

type NavCategory = {
    id: number;
    slug: string;
    translated_name: string;
    image_url: string | null;
    children: Array<{
        id: number;
        slug: string;
        translated_name: string;
    }>;
};

/**
 * The header's "Categories" flyout.
 *
 * With eight main crafts and up to a dozen subcategories each, the old
 * layout — every craft as its own column, all open at once — ran taller than
 * the viewport on anything but a very tall monitor. This shows one craft's
 * subcategories at a time instead, the way most large marketplaces do it: a
 * rail of crafts on one side, the hovered one's children on the other, and a
 * photograph that crossfades in behind the rail so the panel still feels
 * like a place rather than a form. Whatever is left over is capped and
 * scrolls, so a craft with twelve children never pushes the panel off
 * screen.
 */
export default function CategoriesMegaMenu({
    categories,
    activeCategoryId,
    categoryHref,
    onNavigate,
}: {
    categories: NavCategory[];
    /** The `category_id` currently selected on the listing, if any. */
    activeCategoryId: string | null;
    categoryHref: (id: number) => string;
    onNavigate: () => void;
}) {
    const { t } = useTranslation();

    // Whichever craft the pointer (or keyboard focus) is currently over —
    // this is what the photograph and the subcategory list both follow.
    // Reset to the first craft each time the panel mounts, which Radix does
    // fresh on every open, so it never opens mid-way through a stale hover.
    const [hoveredId, setHoveredId] = useState<number>(
        () => categories[0]?.id ?? 0,
    );

    const active = categories.find((c) => c.id === hoveredId) ?? categories[0];

    if (!active) {
        return null;
    }

    return (
        <div className="flex max-h-[min(75vh,34rem)]">
            {/* ── Photograph ──────────────────────────────────────
                Every craft's picture is stacked in the same box and
                crossfaded by opacity — swapping `src` on one `<img>` would
                pop rather than dissolve, and a scale-in on the incoming
                photo keeps the change from reading as a hard cut. */}
            <div className="relative hidden w-44 shrink-0 overflow-hidden lg:block xl:w-52">
                {categories.map((category) => (
                    <div
                        key={category.id}
                        aria-hidden={category.id !== active.id}
                        className={`absolute inset-0 transition-[opacity,transform] duration-500 ease-out ${
                            category.id === active.id
                                ? 'scale-100 opacity-100'
                                : 'scale-105 opacity-0'
                        }`}
                    >
                        {category.image_url ? (
                            <img
                                src={category.image_url}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <WarmFill
                                variant="clay"
                                className="flex h-full w-full items-center justify-center"
                            >
                                <ZellijStarIcon
                                    aria-hidden
                                    className="h-10 w-10 text-souk-brown/25"
                                />
                            </WarmFill>
                        )}
                    </div>
                ))}

                {/* Scrim + label, same treatment as the homepage category
                    tiles, so the flyout and that strip read as one idiom. */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-souk-title/85 via-souk-title/20 to-transparent"
                />
                <p
                    key={active.id}
                    className="absolute inset-x-0 bottom-0 animate-in p-4 font-display text-base leading-tight text-souk-cream duration-300 fade-in slide-in-from-bottom-1"
                >
                    {active.translated_name}
                </p>
            </div>

            {/* ── Craft rail ──────────────────────────────────────
                Capped and independently scrollable so a very long taxonomy
                never grows past the photograph's height. */}
            <div className="w-40 shrink-0 overflow-y-auto border-e border-ink/10 py-2 pe-2 xl:w-48">
                {categories.map((category) => (
                    <Link
                        key={category.id}
                        href={categoryHref(category.id)}
                        onClick={onNavigate}
                        onMouseEnter={() => setHoveredId(category.id)}
                        onFocus={() => setHoveredId(category.id)}
                        aria-current={
                            activeCategoryId === String(category.id)
                                ? 'page'
                                : undefined
                        }
                        className={`group/rail flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm transition-colors ${
                            category.id === active.id
                                ? 'bg-sand/50 font-medium text-terracotta'
                                : 'text-ink/80 hover:bg-sand/30 hover:text-ink'
                        } ${
                            activeCategoryId === String(category.id)
                                ? '!text-terracotta'
                                : ''
                        }`}
                    >
                        <span className="truncate">
                            {category.translated_name}
                        </span>
                        {category.children.length > 0 && (
                            <ChevronRight
                                aria-hidden
                                className={`h-3.5 w-3.5 shrink-0 text-ink-soft/50 transition-transform duration-300 rtl:rotate-180 ${
                                    category.id === active.id
                                        ? 'translate-x-0.5 text-terracotta/70 rtl:-translate-x-0.5'
                                        : 'group-hover/rail:translate-x-0.5 rtl:group-hover/rail:-translate-x-0.5'
                                }`}
                            />
                        )}
                    </Link>
                ))}
            </div>

            {/* ── Subcategories ───────────────────────────────────
                Keyed to the active craft so the fade-in replays on every
                switch — the one visual cue that ties the rail's hover state
                to what just appeared beside it. */}
            <div className="min-h-0 flex-1 overflow-y-auto ps-6">
                <div
                    key={active.id}
                    className="grid animate-in grid-cols-2 gap-x-6 gap-y-1 py-2 duration-300 fade-in slide-in-from-bottom-1"
                >
                    {/* Nothing rendered when a craft has no subcategories
                        yet — the "browse everything in this craft" link
                        below is already the whole offer in that case. */}
                    {active.children.map((child) => (
                        <Link
                            key={child.id}
                            href={categoryHref(child.id)}
                            onClick={onNavigate}
                            aria-current={
                                activeCategoryId === String(child.id)
                                    ? 'page'
                                    : undefined
                            }
                            className="block rounded-md px-2 py-1.5 text-sm text-ink/70 transition-colors hover:bg-sand/30 hover:text-ink aria-[current=page]:font-medium aria-[current=page]:text-terracotta"
                        >
                            {child.translated_name}
                        </Link>
                    ))}
                </div>

                <Link
                    href={categoryHref(active.id)}
                    onClick={onNavigate}
                    className="mt-3 mb-2 inline-flex items-center gap-1.5 border-t border-ink/10 pt-3 text-sm font-medium text-ink-soft transition-colors hover:text-ink"
                >
                    {t('header.view_all_in').replace(
                        ':category',
                        active.translated_name,
                    )}
                    <ChevronRight
                        aria-hidden
                        className="h-3.5 w-3.5 rtl:rotate-180"
                    />
                </Link>
            </div>
        </div>
    );
}
