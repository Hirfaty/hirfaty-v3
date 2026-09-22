import { router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Loader2,
    PackageSearch,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CollectionCard } from '@/components/buyer/CollectionCard';
import type { ListingProduct } from '@/components/buyer/ProductCard';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';

type Filters = {
    search: string;
    category_id: string;
};

/**
 * A leaf in the filter rail — a subcategory. Trimmed server-side to just the
 * fields the rail renders, rather than the whole Category model: `name` is a
 * per-locale object that must never reach the page raw, so only the resolved
 * `translated_name` is sent.
 */
type SubCategory = {
    id: number;
    slug: string;
    translated_name: string;
};

/** A parent craft in the filter rail, with the subcategories nested below it. */
type FilterCategory = {
    id: number;
    slug: string;
    translated_name: string;
    children: SubCategory[];
};

type Props = {
    products: ListingProduct[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        has_more: boolean;
    };
    categories: FilterCategory[];
    filters: Filters;
    audience: 'individual' | 'professional';
    /** Product ids the signed-in buyer has already wishlisted. */
    wishlisted: number[];
    /** False for guests and non-buyers, whom the wishlist route rejects. */
    canWishlist: boolean;
};

/**
 * The nested arch outlines drawn behind the hero copy — a riad doorway seen in
 * elevation. Two of them, offset, so the line reads as a receding arcade
 * rather than a single traced shape.
 */
const HERO_ARCH =
    'pointer-events-none absolute left-1/2 -z-10 -translate-x-1/2 border border-souk-brown/12 [border-radius:50%_50%_0_0/28%_28%_0_0]';

/**
 * Widths of the two arches, kept here rather than inline so the ornaments
 * hung on the doorway below can be sized from the same expressions. A
 * lozenge pinned at a *fixed* x would drift off the jamb the moment the
 * viewport crossed out of the `min()` clamp — pinning the ornaments to a
 * wrapper of the same width instead makes them track the arch at every size.
 */
const ARCH_OUTER_W = 'w-[min(72vw,760px)]';
const ARCH_INNER_W = 'w-[min(66vw,700px)]';

/** Maps the app locale onto a BCP-47 tag for Intl number formatting. */

export default function ProductsIndex({
    products,
    pagination,
    categories,
    filters,
    audience,
    wishlisted,
    canWishlist,
}: Props) {
    const { t, isRTL } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [busy, setBusy] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    /**
     * Which parent crafts have their subcategory rows unfolded in the filter
     * rail. Seeded from the server's current filter so the active branch is
     * already open on first paint; afterwards the rail owns the state, because
     * applyFilters() preserves component state across visits.
     */
    const [expanded, setExpanded] = useState<Record<number, boolean>>(() => {
        if (!filters.category_id) {
            return {};
        }

        const activeId = Number(filters.category_id);
        const parent = categories.find(
            (category) =>
                category.id === activeId ||
                category.children.some((child) => child.id === activeId),
        );

        return parent ? { [parent.id]: true } : {};
    });

    const toggleExpanded = (id: number) =>
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

    const heroTitleRef = useRef<HTMLHeadingElement | null>(null);
    const heroRaf = useRef<number | null>(null);

    /**
     * A touch of parallax on the hero title: as the page scrolls the words
     * drift up faster than the section, leading the scroll out of frame.
     *
     * Written straight onto the node from a rAF-coalesced scroll listener
     * rather than a React state value — a per-frame setState would re-render
     * the whole listing (grid and all) just to move one transform.
     */
    useEffect(() => {
        const onScroll = () => {
            if (heroRaf.current !== null) {
                return;
            }

            heroRaf.current = requestAnimationFrame(() => {
                heroRaf.current = null;

                const node = heroTitleRef.current;

                if (!node) {
                    return;
                }

                const shift = Math.min(window.scrollY, 400) * 0.3;
                node.style.transform = `translate3d(0, ${-shift}px, 0)`;
            });
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);

            if (heroRaf.current !== null) {
                cancelAnimationFrame(heroRaf.current);
            }
        };
    }, []);

    const wished = new Set(wishlisted);

    /*
     * Prices arrive from the server in MAD. `money()` renders them in the
     * currency this visitor is reading (their own pick, or their IP's) and
     * appends the currency itself — so nothing here should add a suffix.
     */
    const { money } = useCurrency();

    /**
     * Any filter change restarts the listing at page 1, so `products` must be
     * reset — without it the merge strategy used by "load more" would append
     * the new results onto the old ones.
     *
     * Deliberately NOT `reset: ['products']`. That option made Inertia apply
     * only the named merge prop and leave every other prop at its previous
     * value, so `filters` and `pagination` went stale on each visit: the grid
     * refreshed, but the active tab never lit up and the result count read
     * against the unfiltered total ("3 of 6" when the filtered total was 3).
     * This is a full visit rather than a partial reload, and a full visit
     * replaces merge props anyway — the append behaviour `reset` was guarding
     * against only applies to the `only:`-scoped reload in loadMore below.
     */
    const applyFilters = (overrides: Partial<Filters> = {}) => {
        const next = {
            search,
            category_id: filters.category_id,
            ...overrides,
        };

        router.get(
            route('products.index'),
            {
                search: next.search || undefined,
                category_id: next.category_id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };

    /**
     * Debounced search. Fires only once the box has actually diverged from
     * what the server already has, which makes it a no-op on mount without
     * needing a "first render" ref.
     *
     * That guard used to be a ref, and StrictMode defeated it: React runs
     * setup → cleanup → setup on the *same* instance, so the ref was already
     * false by the second setup and the effect fired a spurious visit ~300ms
     * after every mount. Because that visit rebuilt its query from the props
     * of the render it closed over, it raced the click that caused the mount
     * and could silently drop the filter just applied — which is why picking
     * a category sometimes appeared not to stick.
     */
    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timer = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search]);

    const loadMore = () => {
        // reload() already forces preserveScroll/preserveState, which is why
        // its options type omits them.
        router.reload({
            data: { page: pagination.current_page + 1 },
            only: ['products', 'pagination'],
            onStart: () => setLoadingMore(true),
            onFinish: () => setLoadingMore(false),
        });
    };

    const hasActiveFilters =
        Boolean(filters.search) || Boolean(filters.category_id);

    return (
        <>
            <Head title="Hirfaty - Catalogue" />
            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ── The full collection ──────────────────────────────── */}
                <section className="mx-auto max-w-[1440px] px-5 py-8 sm:px-8 sm:py-8 lg:px-12">
                    <div className="mb-10 border-b border-souk-brown/15 pb-9">
                        <h2 className="font-display text-4xl text-souk-title sm:text-5xl">
                            {t('buyer.products.collection_title')}
                        </h2>
                    </div>

                    <div className="lg:grid lg:grid-cols-[280px_minmax(0,1fr)] lg:items-start lg:gap-12">
                        <aside className="mb-10 border-b border-souk-brown/15 pb-10 lg:sticky lg:top-8 lg:mb-0 lg:max-h-[calc(100svh-4rem)] lg:overflow-y-auto lg:border-e lg:border-b-0 lg:pe-10 lg:pb-0">
                            <label className="mb-6 flex items-center gap-2 border border-souk-brown/25 bg-souk-cream/40 px-3 py-2 transition-colors focus-within:border-souk-brown">
                                {busy ? (
                                    <Loader2 className="h-4 w-4 shrink-0 animate-spin text-souk-brown/60" />
                                ) : (
                                    <Search
                                        className="h-4 w-4 shrink-0 text-souk-brown/60"
                                        strokeWidth={1.5}
                                    />
                                )}
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder={t(
                                        'buyer.products.search_placeholder',
                                    )}
                                    aria-label={t(
                                        'buyer.products.search_label',
                                    )}
                                    className="min-w-0 flex-1 bg-transparent text-sm text-souk-title outline-none placeholder:text-souk-brown-deep/45"
                                />
                            </label>

                            <h3 className="mb-4 text-[10px] font-semibold tracking-[0.16em] text-souk-brown-deep/50 uppercase">
                                {t('buyer.products.categories')}
                            </h3>

                            <ul className="space-y-1">
                                <li>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            applyFilters({ category_id: '' })
                                        }
                                        aria-pressed={!filters.category_id}
                                        className={`w-full cursor-pointer rounded-sm px-3 py-2 text-start text-sm transition-colors ${
                                            filters.category_id
                                                ? 'text-souk-brown-deep/70 hover:text-souk-brown'
                                                : 'font-semibold text-souk-brown'
                                        }`}
                                    >
                                        {t('buyer.products.category_all')}
                                    </button>
                                </li>

                                {categories.map((category) => {
                                    const categoryId = String(category.id);
                                    const hasChildren =
                                        category.children.length > 0;
                                    const isOpen = Boolean(
                                        expanded[category.id],
                                    );
                                    const isActive =
                                        filters.category_id === categoryId;
                                    const containsActive =
                                        isActive ||
                                        category.children.some(
                                            (child) =>
                                                filters.category_id ===
                                                String(child.id),
                                        );

                                    return (
                                        <li key={category.id}>
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        applyFilters({
                                                            category_id:
                                                                categoryId,
                                                        });
                                                        setExpanded((prev) => ({
                                                            ...prev,
                                                            [category.id]: true,
                                                        }));
                                                    }}
                                                    aria-pressed={isActive}
                                                    className={`min-w-0 flex-1 cursor-pointer truncate rounded-sm px-3 py-2 text-start text-sm transition-colors ${
                                                        containsActive
                                                            ? 'font-semibold text-souk-brown'
                                                            : 'text-souk-brown-deep/70 hover:text-souk-brown'
                                                    }`}
                                                >
                                                    {category.translated_name}
                                                </button>

                                                {hasChildren && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleExpanded(
                                                                category.id,
                                                            )
                                                        }
                                                        aria-expanded={isOpen}
                                                        aria-label={
                                                            isOpen
                                                                ? t(
                                                                      'common.collapse',
                                                                  )
                                                                : t(
                                                                      'common.expand',
                                                                  )
                                                        }
                                                        className="grid size-8 shrink-0 cursor-pointer place-items-center rounded-t-full rounded-b-md text-souk-brown-deep/45 transition-colors hover:text-souk-brown"
                                                    >
                                                        {isOpen ? (
                                                            <ChevronDown className="h-4 w-4" />
                                                        ) : isRTL ? (
                                                            <ChevronLeft className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronRight className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                )}
                                            </div>

                                            {hasChildren && isOpen && (
                                                <ul className="ms-3 mt-1 space-y-1 border-s border-souk-brown/10 ps-3">
                                                    {category.children.map(
                                                        (child) => {
                                                            const childActive =
                                                                filters.category_id ===
                                                                String(
                                                                    child.id,
                                                                );

                                                            return (
                                                                <li
                                                                    key={
                                                                        child.id
                                                                    }
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            applyFilters(
                                                                                {
                                                                                    category_id:
                                                                                        String(
                                                                                            child.id,
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                        aria-pressed={
                                                                            childActive
                                                                        }
                                                                        className={`w-full cursor-pointer truncate rounded-sm px-3 py-1.5 text-start text-sm transition-colors ${
                                                                            childActive
                                                                                ? 'font-semibold text-souk-brown'
                                                                                : 'text-souk-brown-deep/70 hover:text-souk-brown'
                                                                        }`}
                                                                    >
                                                                        {
                                                                            child.translated_name
                                                                        }
                                                                    </button>
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </aside>

                        <div className="min-w-0">
                            <div className="mb-7 flex items-center justify-between gap-4 text-xs text-souk-brown-deep/70">
                                <span>
                                    {pagination.total}{' '}
                                    {t('buyer.products.count_pieces')}
                                </span>
                                <span className="hidden sm:inline">
                                    {t('buyer.products.made_in_morocco')}
                                </span>
                            </div>

                            {products.length === 0 ? (
                                <div className="flex flex-col items-center gap-4 border border-dashed border-souk-brown/20 py-24 text-center">
                                    <PackageSearch
                                        className="h-9 w-9 text-souk-brown/25"
                                        strokeWidth={1.5}
                                    />
                                    <p className="text-souk-brown-deep/70">
                                        {filters.search
                                            ? t(
                                                  'buyer.products.empty_with_search',
                                              )
                                            : t('buyer.products.empty')}
                                    </p>
                                    {hasActiveFilters && (
                                        <button
                                            onClick={() => {
                                                setSearch('');
                                                applyFilters({
                                                    search: '',
                                                    category_id: '',
                                                });
                                            }}
                                            className="border border-souk-brown px-6 py-2.5 text-sm text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream"
                                        >
                                            {t('buyer.products.reset_all')}
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <div
                                    className={`grid grid-cols-2 gap-x-3 gap-y-10 transition-opacity duration-300 sm:gap-x-6 sm:gap-y-14 md:grid-cols-3 lg:gap-x-7 xl:grid-cols-4 ${busy ? 'opacity-50' : ''}`}
                                >
                                    {products.map((p) => (
                                        <CollectionCard
                                            key={p.id}
                                            product={p}
                                            audience={audience}
                                            money={money}
                                            t={t}
                                            wishlisted={wished.has(p.id)}
                                            canWishlist={canWishlist}
                                        />
                                    ))}
                                </div>
                            )}

                            {pagination.has_more && (
                                <div className="mt-16 flex justify-center">
                                    <button
                                        onClick={loadMore}
                                        disabled={loadingMore}
                                        className="inline-flex items-center gap-2 rounded-md border border-souk-brown px-8 py-3.5 text-sm text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-50"
                                    >
                                        {loadingMore && (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        )}
                                        {loadingMore
                                            ? t('buyer.products.loading')
                                            : t('buyer.products.load_more')}
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* ── Closing band ─────────────────────────────────────
                One dark full-bleed rule between the last row of tiles and the
                footer, so the grid ends rather than simply stopping. */}
                <section className="border-t border-souk-brown bg-souk-brown px-5 py-16 text-souk-cream sm:px-8 lg:px-12">
                    <div className="mx-auto max-w-[1440px] text-center md:text-end">
                        <p className="font-display text-3xl text-souk-cream sm:text-4xl">
                            {t('buyer.products.closing_title')}
                        </p>
                        <p className="mt-3 text-sm text-souk-cream/75">
                            {t('buyer.products.closing_subtitle')}
                        </p>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
