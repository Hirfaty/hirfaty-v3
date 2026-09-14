import { router } from '@inertiajs/react';
import { Loader2, PackageSearch, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CollectionCard } from '@/components/buyer/CollectionCard';
import type { ListingProduct } from '@/components/buyer/ProductCard';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

type Filters = {
    search: string;
    category_id: string;
};

/**
 * Trimmed server-side to just what the tab row renders, rather than the whole
 * Category model — `name` is a per-locale object that must never reach the
 * page raw, so only the resolved `translated_name` is sent.
 */
type TabCategory = {
    id: number;
    slug: string;
    translated_name: string;
};

type Props = {
    products: ListingProduct[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        has_more: boolean;
    };
    categories: TabCategory[];
    filters: Filters;
    audience: 'individual' | 'professional';
    /** Product ids the signed-in buyer has already wishlisted. */
    wishlisted: number[];
    /** False for guests and non-buyers, whom the wishlist route rejects. */
    canWishlist: boolean;
};

/** Museum-caption label, shared with the cards below. */
const EYEBROW = 'text-[10px] font-semibold uppercase tracking-[0.16em]';

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
const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

export default function ProductsIndex({
    products,
    pagination,
    categories,
    filters,
    audience,
    wishlisted,
    canWishlist,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [busy, setBusy] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    const wished = new Set(wishlisted);

    const money = (value: number | string) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            maximumFractionDigits: 0,
        }).format(Number(value));

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
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/* ── Hero ──────────────────────────────────────────────
                No photograph: the listing's own grid supplies the colour a
                few screens down, and a picture here only competed with it.
                What is left is type, centred inside a traced arcade. */}
            <section className="relative isolate overflow-hidden border-b border-souk-brown/15 px-5 sm:px-8 sm:py-20 lg:px-12 lg:py-24">
                <div
                    aria-hidden="true"
                    className={`${HERO_ARCH} top-7 h-[115%] ${ARCH_OUTER_W}`}
                />
                <div
                    aria-hidden="true"
                    className={`${HERO_ARCH} top-11 h-[115%] ${ARCH_INNER_W}`}
                />

                {/* ── The doorway's ornament ────────────────────────────
                    Three touches, one at each point a real riad doorway is
                    actually decorated — the crown, the jambs and the
                    threshold — rather than pattern laid over the whole
                    opening. All of it stays line-weight and `-z-10`, behind
                    the type: the hero earns its restraint from being type on
                    paper, and a filled motif here would take that away.

                    Every piece is centred (`left-1/2 -translate-x-1/2`) or
                    pinned to a wrapper of the arch's own width, so the
                    ornament needs no RTL handling and tracks the arch as it
                    scales. */}

                {/* Keystone at the crown, hung just under the inner arch's
                    apex. A star rather than the line–lozenge–line rule used
                    between title and subtitle below: repeating that ornament
                    here would read as a duplicate rather than as a second,
                    distinct point of decoration. */}
                <ZellijStarIcon className="pointer-events-none absolute top-12 left-1/2 -z-10 size-6 -translate-x-1/2 text-souk-brown/25" />

                {/* Studs on the jambs, down in the clear space below the
                    subtitle — the pair of small glazed tiles set into a door
                    frame. Each is pulled out by half its own width so it
                    straddles the arch line and reads as set *into* the jamb
                    rather than parked beside it.

                    `left`/`right` deliberately, not `start`/`end`: the offset
                    has to be a *physical* translate (`translate-x` has no
                    logical form), so a logical edge would flip under
                    `dir="rtl"` while the translate stayed put — which parked
                    both studs 8px inboard of the jambs instead of on them.
                    The pair is identical and the wrapper is centred, so there
                    is no start/end meaning here to preserve anyway. */}
                <div
                    aria-hidden="true"
                    className={`pointer-events-none absolute bottom-14 left-1/2 -z-10 -translate-x-1/2 ${ARCH_OUTER_W}`}
                >
                    <span className="absolute left-0 size-2 -translate-x-1/2 rotate-45 border border-souk-brown/30" />
                    <span className="absolute right-0 size-2 translate-x-1/2 rotate-45 border border-souk-brown/30" />
                </div>

                <div className="mx-auto max-w-4xl text-center">
                    <h1 className="font-display text-5xl leading-[0.98] text-balance text-souk-brown sm:text-7xl lg:text-[5.4rem]">
                        {t('buyer.products.hero_title')}
                    </h1>

                    {/* Line — lozenge — line: the same ornament that separates
                        stanzas of tilework, standing in for a rule. */}
                    <div
                        aria-hidden="true"
                        className="mx-auto my-5 flex w-40 items-center gap-3"
                    >
                        <span className="h-px flex-1 bg-souk-brown/25" />
                        <span className="size-2 rotate-45 border border-souk-brown/50" />
                        <span className="h-px flex-1 bg-souk-brown/25" />
                    </div>

                    <p className="mx-auto max-w-2xl text-sm leading-7 text-balance text-souk-brown-deep/80 sm:text-base">
                        {t('buyer.products.hero_subtitle')}
                    </p>
                </div>
            </section>

            {/* ── The full collection ──────────────────────────────── */}
            <section className="mx-auto max-w-[1440px] px-5 py-8 sm:px-8 sm:py-8 lg:px-12">
                {/* Heading and search share a baseline: the search field is
                    an underlined line of display type, not a boxed input, so
                    it sits in the masthead as a second title rather than as a
                    widget bolted beneath one. */}
                <div className="mb-10 grid gap-7 border-b border-souk-brown/15 pb-9 lg:grid-cols-[minmax(0,0.75fr)_minmax(380px,1fr)] lg:items-end">
                    <div>
                        <h2 className="font-display text-4xl text-souk-title sm:text-5xl">
                            {t('buyer.products.collection_title')}
                        </h2>
                    </div>

                    <label className="flex items-center gap-3 border-b border-souk-brown pb-3">
                        {busy ? (
                            <Loader2 className="h-5 w-5 shrink-0 animate-spin text-souk-brown/60" />
                        ) : (
                            <Search
                                className="h-5 w-5 shrink-0 text-souk-brown/60"
                                strokeWidth={1.5}
                            />
                        )}
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('buyer.products.search_placeholder')}
                            aria-label={t('buyer.products.search_label')}
                            className="min-w-0 flex-1 bg-transparent font-display text-xl text-souk-title outline-none placeholder:text-souk-brown-deep/45"
                        />
                    </label>
                </div>

                {/* ── Category tabs ────────────────────────────────
                    Text tabs rather than the old glyph strip: eight drawings
                    competed with the eight photographs directly below them.
                    Scrolls sideways below lg, where the row cannot fit. */}
                <div className="mb-10 overflow-x-auto border-b border-souk-brown/15 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <div className="flex min-w-max gap-7">
                        <button
                            type="button"
                            onClick={() => applyFilters({ category_id: '' })}
                            aria-pressed={!filters.category_id}
                            className={`relative cursor-pointer pb-4 text-sm transition-colors ${
                                filters.category_id
                                    ? 'text-souk-brown-deep/70 hover:text-souk-brown'
                                    : "font-semibold text-souk-brown after:absolute after:start-0 after:bottom-0 after:h-0.5 after:w-full after:bg-souk-brown after:content-['']"
                            }`}
                        >
                            {t('buyer.products.category_all')}
                        </button>

                        {categories.map((category) => {
                            const value = String(category.id);
                            const active = filters.category_id === value;

                            return (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() =>
                                        applyFilters({ category_id: value })
                                    }
                                    aria-pressed={active}
                                    className={`relative cursor-pointer pb-4 text-sm transition-colors ${
                                        active
                                            ? "font-semibold text-souk-brown after:absolute after:start-0 after:bottom-0 after:h-0.5 after:w-full after:bg-souk-brown after:content-['']"
                                            : 'text-souk-brown-deep/70 hover:text-souk-brown'
                                    }`}
                                >
                                    {category.translated_name}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="mb-7 flex items-center justify-between gap-4 text-xs text-souk-brown-deep/70">
                    <span>
                        {pagination.total} {t('buyer.products.count_pieces')}
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
                                ? t('buyer.products.empty_with_search')
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
                        className={`grid grid-cols-2 gap-x-3 gap-y-10 transition-opacity duration-300 sm:gap-x-6 sm:gap-y-14 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-7 ${busy ? 'opacity-50' : ''}`}
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
    );
}
