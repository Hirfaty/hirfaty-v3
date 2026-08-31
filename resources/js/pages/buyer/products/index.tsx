import { Link, router } from '@inertiajs/react';
import {
    BadgePercent,
    Check,
    Loader2,
    PackageSearch,
    Search,
    SlidersHorizontal,
    Star,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import type { Category, Media } from '@/types';

/**
 * Shape of one row from BuyerProductResource.
 *
 * Declared here rather than reusing the shared `BuyerProductResource` type:
 * the professional block is conditionally merged server-side (only trade
 * accounts receive it), which an `Omit<Product, …>` cannot express.
 */
type ListingProduct = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    base_price: string;
    sale_price: string | null;
    stock: number;
    has_variants: boolean;
    average_rating: string | null;
    category: { id: number; translated_name: string } | null;
    store: {
        id: number | null;
        slug: string | null;
        store_name: string | null;
        city_name: string | null;
    };
    images: Media[];
    wishlists_count?: number;

    // Present only for professional buyers.
    professional_price?: string | null;
    professional_sale_price?: string | null;
    professional_effective_price?: number | null;
    min_order_qty_professional?: number;
};

type Filters = {
    search: string;
    category_id: string;
    max_price: string;
    min_rating: string;
    in_stock: boolean;
    sort: string;
};

type Props = {
    products: ListingProduct[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        has_more: boolean;
    };
    categories: Category[];
    filters: Filters;
    audience: 'individual' | 'professional';
    priceCeiling: number;
};

const SORTS = [
    { value: 'score', label: 'Pertinence' },
    { value: 'latest', label: 'Nouveautés' },
    { value: 'price_asc', label: 'Prix croissant' },
    { value: 'price_desc', label: 'Prix décroissant' },
    { value: 'rating', label: 'Mieux notés' },
] as const;

const money = (value: number | string) =>
    new Intl.NumberFormat('fr-MA', { maximumFractionDigits: 0 }).format(
        Number(value),
    );

/** Museum-caption label, per the design system's label styling. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

/**
 * Price for one card.
 *
 * Retail stays the headline figure for *every* audience because retail is
 * what the checkout actually bills — `CheckoutService` has no notion of the
 * trade list yet. A professional additionally sees their reference rate
 * underneath, explicitly marked indicative, so the page never quotes a
 * number the basket won't honour.
 */
function PriceBlock({
    product,
    audience,
}: {
    product: ListingProduct;
    audience: Props['audience'];
}) {
    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = sale !== null && sale < base;
    const retail = onSale ? sale : base;

    const pro = product.professional_effective_price ?? null;
    const showPro = audience === 'professional' && pro !== null && pro < retail;
    const saving = showPro ? Math.round((1 - pro / retail) * 100) : 0;
    const minQty = product.min_order_qty_professional ?? 1;

    return (
        <div className="text-right">
            <div className="flex items-baseline justify-end gap-2">
                <span
                    className={`font-display text-lg leading-none ${onSale ? 'text-terracotta' : 'text-souk-title'}`}
                >
                    {money(retail)}
                    <span className="ms-1 text-[11px]">MAD</span>
                </span>
                {onSale && (
                    <span className="text-xs text-souk-brown-deep/45 line-through">
                        {money(base)}
                    </span>
                )}
            </div>

            {showPro && (
                <div
                    className="mt-1.5 inline-flex flex-wrap items-baseline justify-end gap-x-1.5 rounded-full bg-olive/10 px-2.5 py-1"
                    title="Tarif professionnel indicatif — la facturation reste au prix public pour le moment."
                >
                    <span className="text-[9px] tracking-[0.16em] text-olive/80 uppercase">
                        Pro*
                    </span>
                    <span className="font-display text-sm leading-none text-olive">
                        {money(pro)}
                    </span>
                    {saving > 0 && (
                        <span className="text-[10px] font-medium text-olive">
                            −{saving}%
                        </span>
                    )}
                    {minQty > 1 && (
                        <span className="text-[10px] text-olive/70">
                            dès {minQty}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Arch-topped tile, echoing the horseshoe arches used on the home page and
 * in the `motif-arches` background — the shape is the brand's signature, and
 * keeps the grid free of the hard corners the old listing had.
 */
function ProductCard({
    product,
    audience,
}: {
    product: ListingProduct;
    audience: Props['audience'];
}) {
    const cover = product.images[0]?.url;
    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = sale !== null && sale < base;
    const rating = Number(product.average_rating ?? 0);
    const soldOut = product.stock === 0;

    return (
        <Link href={`/products/${product.slug}`} className="group block">
            <div className="relative aspect-[4/5] overflow-hidden rounded-t-[6rem] rounded-b-3xl bg-souk-brown/5 ring-1 ring-souk-brown/10 transition-shadow duration-500 group-hover:shadow-xl group-hover:shadow-souk-brown/15">
                {cover ? (
                    <img
                        src={cover}
                        alt={product.name}
                        loading="lazy"
                        className={`h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05] ${soldOut ? 'opacity-60' : ''}`}
                    />
                ) : (
                    <div className="flex h-full w-full flex-col items-center justify-center gap-2 text-souk-brown/30">
                        <ZellijStarIcon className="h-8 w-8" />
                        <span className="text-[10px] tracking-[0.16em] uppercase">
                            Sans image
                        </span>
                    </div>
                )}

                {onSale && (
                    <span
                        className={`absolute top-4 left-4 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                    >
                        Promo
                    </span>
                )}
                {soldOut && (
                    <span
                        className={`absolute top-4 right-4 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                    >
                        Épuisé
                    </span>
                )}
            </div>

            <div className="mt-4 px-1">
                <p className={`truncate text-souk-brown-deep/55 ${EYEBROW}`}>
                    {product.store.store_name}
                    {product.category &&
                        ` · ${product.category.translated_name}`}
                </p>

                <div className="mt-2 flex items-start justify-between gap-3">
                    <h3 className="font-display text-lg leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                        {product.name}
                    </h3>
                    <div className="shrink-0">
                        <PriceBlock product={product} audience={audience} />
                    </div>
                </div>

                {rating > 0 && (
                    <div className="mt-2 flex items-center gap-1 text-xs text-souk-brown-deep/60">
                        <Star
                            className="h-3 w-3 fill-terracotta text-terracotta"
                            strokeWidth={1.5}
                        />
                        {rating.toFixed(1)}
                    </div>
                )}
            </div>
        </Link>
    );
}

export default function ProductsIndex({
    products,
    pagination,
    categories,
    filters,
    audience,
    priceCeiling,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [maxPrice, setMaxPrice] = useState(
        filters.max_price || String(priceCeiling),
    );
    const [busy, setBusy] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    // Skip the debounce on mount, otherwise landing with ?search= already set
    // fires a redundant request for results the server just rendered.
    const isFirstRender = useRef(true);

    /**
     * Any filter change restarts the listing at page 1, so `products` must be
     * reset — without it the merge strategy used by "load more" would append
     * the new results onto the old ones.
     */
    const applyFilters = (overrides: Partial<Filters> = {}) => {
        const next = {
            search,
            category_id: filters.category_id,
            max_price: maxPrice === String(priceCeiling) ? '' : maxPrice,
            min_rating: filters.min_rating,
            in_stock: filters.in_stock,
            sort: filters.sort,
            ...overrides,
        };

        router.get(
            route('products.index'),
            {
                search: next.search || undefined,
                category_id: next.category_id || undefined,
                max_price: next.max_price || undefined,
                min_rating: next.min_rating || undefined,
                in_stock: next.in_stock ? 1 : undefined,
                sort: next.sort === 'score' ? undefined : next.sort,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                reset: ['products'],
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

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

    const resetAll = () => {
        setSearch('');
        setMaxPrice(String(priceCeiling));
        router.get(
            route('products.index'),
            {},
            { preserveScroll: true, reset: ['products'] },
        );
    };

    const hasActiveFilters =
        Boolean(filters.search) ||
        Boolean(filters.category_id) ||
        Boolean(filters.max_price) ||
        Boolean(filters.min_rating) ||
        filters.in_stock ||
        filters.sort !== 'score';

    const toggleRating = (n: number) =>
        applyFilters({
            min_rating: String(n) === filters.min_rating ? '' : String(n),
        });

    // ── Refinements, shared by the sidebar and the mobile drawer ──
    const refinements = (
        <div className="space-y-9">
            <div>
                <div className="mb-4 flex items-baseline justify-between">
                    <p className={`text-souk-brown-deep/60 ${EYEBROW}`}>Prix</p>
                    <span className="font-display text-sm text-souk-title">
                        ≤ {money(maxPrice)} MAD
                    </span>
                </div>
                <input
                    type="range"
                    min={0}
                    max={priceCeiling}
                    step={10}
                    value={maxPrice}
                    onChange={(e) => setMaxPrice(e.target.value)}
                    onMouseUp={() => applyFilters()}
                    onTouchEnd={() => applyFilters()}
                    aria-label="Prix maximum"
                    className="w-full accent-terracotta"
                />
                <div className="mt-2 flex justify-between text-[11px] text-souk-brown-deep/50">
                    <span>0</span>
                    <span>{money(priceCeiling)} MAD</span>
                </div>
            </div>

            <div>
                <p className={`mb-3 text-souk-brown-deep/60 ${EYEBROW}`}>
                    Note minimum
                </p>
                <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map((n) => (
                        <button
                            key={n}
                            onClick={() => toggleRating(n)}
                            aria-label={`${n} étoile${n > 1 ? 's' : ''} minimum`}
                            className="rounded-full p-1 transition-colors hover:bg-souk-brown/5"
                        >
                            <Star
                                className={`h-4 w-4 ${n <= Number(filters.min_rating) ? 'fill-terracotta text-terracotta' : 'text-souk-brown/25'}`}
                                strokeWidth={1.5}
                            />
                        </button>
                    ))}
                </div>
            </div>

            <button
                onClick={() => applyFilters({ in_stock: !filters.in_stock })}
                className="flex items-center gap-3 text-sm text-souk-brown-deep/80 transition-colors hover:text-souk-title"
            >
                <span
                    className={`flex h-4 w-4 items-center justify-center rounded-md border transition-colors ${
                        filters.in_stock
                            ? 'border-souk-brown bg-souk-brown'
                            : 'border-souk-brown/30'
                    }`}
                >
                    {filters.in_stock && (
                        <Check className="h-3 w-3 text-souk-cream" />
                    )}
                </span>
                En stock uniquement
            </button>

            {hasActiveFilters && (
                <button
                    onClick={resetAll}
                    className="rounded-full border border-souk-brown/20 px-4 py-2 text-sm text-souk-brown-deep transition-colors hover:border-souk-brown hover:bg-souk-brown hover:text-souk-cream"
                >
                    Tout réinitialiser
                </button>
            )}
        </div>
    );

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/* ── Hero ─────────────────────────────────────────
                Compact on purpose: this is a browsing page, so the grid
                should not be pushed below the fold. */}
            <section className="relative overflow-hidden rounded-b-[2.5rem] bg-souk-brown lg:rounded-br-[18rem]">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-1/3 -left-1/4 h-[140%] w-[70%] rounded-full bg-[radial-gradient(closest-side,rgba(240,228,201,0.16),transparent)]"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 motif-zellij opacity-50 [--pattern-color:rgba(240,228,201,0.16)] [--pattern-size:72px]"
                />

                <div className="relative container py-12 md:py-16">
                    <div className="flex flex-wrap items-end justify-between gap-6">
                        <div className="max-w-2xl">
                            <p
                                className={`flex items-center gap-3 text-souk-cream/70 ${EYEBROW}`}
                            >
                                <ZellijStarIcon className="h-3.5 w-3.5" />
                                Le marché
                            </p>
                            <h1 className="mt-4 font-display text-[clamp(2.25rem,5vw,4rem)] leading-[1.04] tracking-[-0.02em] text-white">
                                Tout ce qui se fait{' '}
                                <span className="text-souk-cream italic">
                                    à la main
                                </span>
                            </h1>
                            <p className="mt-4 max-w-lg leading-relaxed text-souk-cream/75">
                                Pièces façonnées dans les ateliers du Maroc, et
                                vendues directement par les artisans.
                            </p>
                        </div>

                        <dl className="flex shrink-0 items-end gap-8 border-s border-souk-cream/20 ps-8">
                            <div>
                                <dt className="font-display text-3xl text-white">
                                    {pagination.total}
                                </dt>
                                <dd
                                    className={`mt-1 text-souk-cream/60 ${EYEBROW}`}
                                >
                                    pièce{pagination.total > 1 ? 's' : ''}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-display text-3xl text-white">
                                    {categories.length}
                                </dt>
                                <dd
                                    className={`mt-1 text-souk-cream/60 ${EYEBROW}`}
                                >
                                    métiers
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            {/* ── Search + sort, pinned so they stay reachable ── */}
            <div className="sticky top-0 z-30 border-b border-souk-brown/10 bg-souk-paper/90 backdrop-blur">
                <div className="container flex flex-wrap items-center gap-3 py-4">
                    <div className="relative min-w-0 flex-1">
                        {busy ? (
                            <Loader2 className="absolute top-1/2 left-5 h-4 w-4 -translate-y-1/2 animate-spin text-souk-brown/60" />
                        ) : (
                            <Search
                                className="absolute top-1/2 left-5 h-4 w-4 -translate-y-1/2 text-souk-brown/50"
                                strokeWidth={1.5}
                            />
                        )}
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher une pièce, une matière, un atelier…"
                            aria-label="Rechercher un produit"
                            className="w-full rounded-full border border-souk-brown/15 bg-souk-peach/50 py-3 pr-11 pl-12 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/45 focus:border-souk-brown/45 focus:bg-souk-peach focus:outline-none"
                        />
                        {search && (
                            <button
                                onClick={() => setSearch('')}
                                aria-label="Effacer la recherche"
                                className="absolute top-1/2 right-4 -translate-y-1/2 rounded-full p-1 text-souk-brown/50 transition-colors hover:bg-souk-brown/10 hover:text-souk-title"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <label className="flex shrink-0 items-center gap-2 text-sm text-souk-brown-deep/70">
                        <span className="hidden sm:inline">Trier</span>
                        <select
                            value={filters.sort}
                            onChange={(e) =>
                                applyFilters({ sort: e.target.value })
                            }
                            className="rounded-full border border-souk-brown/15 bg-souk-paper px-4 py-3 text-sm text-souk-title transition-colors focus:border-souk-brown/45 focus:outline-none"
                        >
                            {SORTS.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>

            {/* ── Category pills ──────────────────────────────── */}
            <div className="container pt-7">
                <div className="flex flex-wrap gap-2">
                    {[
                        { id: '', translated_name: 'Tout le marché' },
                        ...categories,
                    ].map((c) => {
                        const value = String(c.id);
                        const active = filters.category_id === value;

                        return (
                            <button
                                key={value || 'all'}
                                onClick={() =>
                                    applyFilters({ category_id: value })
                                }
                                className={`rounded-full border px-4 py-2 text-sm transition-colors ${
                                    active
                                        ? 'border-souk-brown bg-souk-brown text-souk-cream'
                                        : 'border-souk-brown/15 bg-souk-peach/40 text-souk-brown-deep hover:border-souk-brown/40 hover:bg-souk-peach'
                                }`}
                            >
                                {c.translated_name}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ── Audience notice ─────────────────────────────── */}
            <div className="container pt-6">
                {audience === 'professional' ? (
                    <div className="flex items-start gap-3 rounded-2xl border border-olive/25 bg-olive/5 px-5 py-4 text-sm">
                        <BadgePercent
                            className="mt-0.5 h-4 w-4 shrink-0 text-olive"
                            strokeWidth={1.5}
                        />
                        <p className="text-souk-brown-deep/80">
                            <span className="font-medium text-souk-title">
                                Compte professionnel.
                            </span>{' '}
                            Vos tarifs pro apparaissent sous le prix public,
                            marqués <span className="text-olive">Pro*</span>.
                            Ils sont indicatifs : la facturation reste au prix
                            public pour le moment.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-souk-brown/12 bg-souk-peach/50 px-5 py-4 text-sm">
                        <p className="text-souk-brown-deep/80">
                            Vous achetez pour un riad, un hôtel ou une boutique
                            ? Des tarifs professionnels existent sur une grande
                            partie du catalogue.
                        </p>
                        <Link
                            href={route('register')}
                            className="shrink-0 rounded-full bg-souk-brown px-5 py-2 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            Ouvrir un compte pro
                        </Link>
                    </div>
                )}
            </div>

            <section className="container grid grid-cols-12 gap-8 py-9 md:gap-12 md:py-12">
                {/* Mobile refinements */}
                <div className="col-span-12 md:hidden">
                    <details className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 p-5">
                        <summary className="flex cursor-pointer list-none items-center justify-between text-sm text-souk-title">
                            <span
                                className={`flex items-center gap-2 text-souk-brown-deep/70 ${EYEBROW}`}
                            >
                                <SlidersHorizontal className="h-3.5 w-3.5" />
                                Affiner
                            </span>
                            <span className="text-xs text-souk-brown-deep/50">
                                Ouvrir
                            </span>
                        </summary>
                        <div className="mt-6">{refinements}</div>
                    </details>
                </div>

                {/* Sidebar */}
                <aside className="col-span-12 hidden md:col-span-3 md:block">
                    <div className="sticky top-24 rounded-3xl border border-souk-brown/12 bg-souk-peach/40 p-6">
                        <p
                            className={`mb-6 flex items-center gap-2 text-souk-brown-deep/70 ${EYEBROW}`}
                        >
                            <SlidersHorizontal className="h-3.5 w-3.5" />
                            Affiner
                        </p>
                        {refinements}
                    </div>
                </aside>

                {/* Grid */}
                <div className="col-span-12 md:col-span-9">
                    {products.length === 0 ? (
                        <div className="flex flex-col items-center gap-4 rounded-3xl border border-dashed border-souk-brown/20 bg-souk-peach/30 py-24 text-center">
                            <PackageSearch
                                className="h-9 w-9 text-souk-brown/25"
                                strokeWidth={1.5}
                            />
                            <p className="text-souk-brown-deep/70">
                                {filters.search
                                    ? `Aucune pièce ne correspond à « ${filters.search} ».`
                                    : 'Aucune pièce ne correspond à vos filtres.'}
                            </p>
                            {hasActiveFilters && (
                                <button
                                    onClick={resetAll}
                                    className="rounded-full bg-souk-brown px-6 py-2.5 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                                >
                                    Tout réinitialiser
                                </button>
                            )}
                        </div>
                    ) : (
                        <div
                            className={`grid grid-cols-1 gap-x-6 gap-y-10 transition-opacity duration-300 sm:grid-cols-2 lg:grid-cols-3 ${busy ? 'opacity-50' : ''}`}
                        >
                            {products.map((p) => (
                                <ProductCard
                                    key={p.id}
                                    product={p}
                                    audience={audience}
                                />
                            ))}
                        </div>
                    )}

                    {products.length > 0 && (
                        <div className="mt-12 flex flex-col items-center gap-4">
                            {pagination.has_more && (
                                <button
                                    onClick={loadMore}
                                    disabled={loadingMore}
                                    className="inline-flex items-center gap-2 rounded-full border border-souk-brown px-8 py-3.5 text-sm text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-50"
                                >
                                    {loadingMore && (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    )}
                                    {loadingMore
                                        ? 'Chargement…'
                                        : 'Voir plus de pièces'}
                                </button>
                            )}
                            <p className={`text-souk-brown-deep/50 ${EYEBROW}`}>
                                {products.length} sur {pagination.total}
                            </p>
                        </div>
                    )}
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
