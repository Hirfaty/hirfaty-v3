import { Link, router } from '@inertiajs/react';
import {
    BadgePercent,
    Check,
    Hand,
    Leaf,
    Loader2,
    PackageSearch,
    Search,
    SlidersHorizontal,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import HeroCarousel from '@/components/HeroCarousel';
import {
    ArganIcon,
    ArtIcon,
    JewelryIcon,
    LeatherIcon,
    PotteryIcon,
    SpiceIcon,
    TextileIcon,
    WoodIcon,
    ZellijStarIcon,
} from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import heroMain from '@/images/hero-main.jpg';
import heroSpice from '@/images/hero-spice.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';
import type { Media } from '@/types';

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

/**
 * Trimmed server-side to just what the strip renders, rather than the whole
 * Category model — `name` is a per-locale object that must never reach the
 * page raw, so only the resolved `translated_name` is sent.
 */
type StripCategory = {
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
    categories: StripCategory[];
    filters: Filters;
    audience: 'individual' | 'professional';
    priceCeiling: number;
};

/**
 * Sort values map to translation keys rather than literal labels, so the
 * dropdown follows the active locale like the rest of the page.
 */
const SORTS = [
    { value: 'score', key: 'buyer.products.sort_score' },
    { value: 'latest', key: 'buyer.products.sort_latest' },
    { value: 'price_asc', key: 'buyer.products.sort_price_asc' },
    { value: 'price_desc', key: 'buyer.products.sort_price_desc' },
    { value: 'rating', key: 'buyer.products.sort_rating' },
] as const;

/**
 * Hero carousel slides. Alt text is stored as a translation key and resolved
 * at render, so the descriptions follow the active locale like everything
 * else on the page.
 */
const HERO_SLIDES = [
    { src: heroMain, altKey: 'buyer.products.hero_slide_pottery' },
    { src: heroTextile, altKey: 'buyer.products.hero_slide_textile' },
    { src: heroZellige, altKey: 'buyer.products.hero_slide_zellige' },
    { src: heroSpice, altKey: 'buyer.products.hero_slide_spice' },
] as const;

/**
 * The four promises shown under the hero copy. ZellijStarIcon is the brand's
 * own ornament and stands in for "unique & authentic"; the rest come from
 * lucide, matching the icon set used everywhere else.
 */
const HERO_FEATURES = [
    {
        icon: Hand,
        labelKey: 'buyer.products.feature_handmade',
        subKey: 'buyer.products.feature_handmade_sub',
    },
    {
        icon: Leaf,
        labelKey: 'buyer.products.feature_natural',
        subKey: 'buyer.products.feature_natural_sub',
    },
    {
        icon: ZellijStarIcon,
        labelKey: 'buyer.products.feature_unique',
        subKey: 'buyer.products.feature_unique_sub',
    },
    {
        icon: Users,
        labelKey: 'buyer.products.feature_artisans',
        subKey: 'buyer.products.feature_artisans_sub',
    },
] as const;

/**
 * Category slug → glyph. Keyed on slug rather than id so it survives a
 * reseed, and falls back to the zellij star for any category added later
 * without a drawing of its own.
 */
const CATEGORY_ICONS: Record<string, typeof ZellijStarIcon> = {
    'poterie-ceramique': PotteryIcon,
    maroquinerie: LeatherIcon,
    'textiles-tissage': TextileIcon,
    'bijoux-accessoires': JewelryIcon,
    'bois-menuiserie': WoodIcon,
    'epices-alimentation': SpiceIcon,
    'peinture-art': ArtIcon,
    'cosmetiques-argan': ArganIcon,
};

/** Museum-caption label, per the design system's label styling. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

/** Maps the app locale onto a BCP-47 tag for Intl number formatting. */
const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

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
    money,
    t,
}: {
    product: ListingProduct;
    audience: Props['audience'];
    money: (value: number | string) => string;
    t: (key: string) => string;
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
        <div className="text-end">
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
                    title={t('buyer.products.pro_price_hint')}
                >
                    <span className="text-[9px] tracking-[0.16em] text-olive/80 uppercase">
                        {t('buyer.products.pro_short')}
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
                            {t('buyer.products.pro_from_qty')} {minQty}
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
    money,
    t,
}: {
    product: ListingProduct;
    audience: Props['audience'];
    money: (value: number | string) => string;
    t: (key: string) => string;
}) {
    const cover = product.images[0]?.url;
    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = sale !== null && sale < base;
    const soldOut = product.stock === 0;

    return (
        <Link href={`/products/${product.slug}`} className="group block">
            <div className="relative aspect-[4/5] overflow-hidden rounded-t-[6rem] rounded-b-3xl bg-souk-brown/5 ring-1 ring-souk-brown/10 transition-all duration-500 group-hover:-translate-y-1 group-hover:shadow-xl group-hover:shadow-souk-brown/15 group-hover:ring-souk-brown/25">
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
                            {t('buyer.products.no_image')}
                        </span>
                    </div>
                )}

                {onSale && (
                    <span
                        className={`absolute start-4 top-4 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                    >
                        {t('buyer.products.badge_sale')}
                    </span>
                )}
                {soldOut && (
                    <span
                        className={`absolute end-4 top-4 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                    >
                        {t('buyer.products.badge_sold_out')}
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
                        <PriceBlock
                            product={product}
                            audience={audience}
                            money={money}
                            t={t}
                        />
                    </div>
                </div>
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
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [maxPrice, setMaxPrice] = useState(
        filters.max_price || String(priceCeiling),
    );
    const [busy, setBusy] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    const money = (value: number | string) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            maximumFractionDigits: 0,
        }).format(Number(value));

    /**
     * Any filter change restarts the listing at page 1, so `products` must be
     * reset — without it the merge strategy used by "load more" would append
     * the new results onto the old ones.
     *
     * min_rating no longer has any UI but is still passed through so an
     * existing deep link keeps filtering while other controls are adjusted;
     * the server still honours it.
     *
     * Deliberately NOT `reset: ['products']`. That option made Inertia apply
     * only the named merge prop and leave every other prop at its previous
     * value, so `filters` and `pagination` went stale on each visit: the grid
     * refreshed, but the active sort/category never lit up and the result
     * count read against the unfiltered total ("3 sur 6" when the filtered
     * total was 3). This is a full visit rather than a partial reload, and a
     * full visit replaces merge props anyway — the append behaviour `reset`
     * was guarding against only applies to the `only:`-scoped reload in
     * loadMore below.
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
     * a sort or a category sometimes appeared not to stick.
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

    const resetAll = () => {
        setSearch('');
        setMaxPrice(String(priceCeiling));
        router.get(
            route('products.index'),
            {},
            // No `reset` here either — see the note on applyFilters.
            { preserveScroll: true },
        );
    };

    const hasActiveFilters =
        Boolean(filters.search) ||
        Boolean(filters.category_id) ||
        Boolean(filters.max_price) ||
        Boolean(filters.min_rating) ||
        filters.in_stock ||
        filters.sort !== 'score';

    // ── Refinements, shared by the sidebar and the mobile drawer ──
    const refinements = (
        <div className="space-y-8">
            {/* Sort lives here rather than beside the search field, keeping
                every control that narrows the grid in one place. */}
            <div>
                <p className={`mb-3 text-souk-brown-deep/60 ${EYEBROW}`}>
                    {t('buyer.products.sort')}
                </p>
                <div className="space-y-1">
                    {SORTS.map((s) => {
                        const active = filters.sort === s.value;

                        return (
                            <button
                                key={s.value}
                                onClick={() => applyFilters({ sort: s.value })}
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-start text-sm transition-colors ${
                                    active
                                        ? 'bg-souk-brown text-souk-cream'
                                        : 'text-souk-brown-deep/80 hover:bg-souk-brown/8 hover:text-souk-title'
                                }`}
                            >
                                {t(s.key)}
                                {active && (
                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="h-px bg-souk-brown/10" />

            <div>
                <div className="mb-4 flex items-baseline justify-between gap-3">
                    <p className={`text-souk-brown-deep/60 ${EYEBROW}`}>
                        {t('buyer.products.price')}
                    </p>
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
                    aria-label={t('buyer.products.price_max_label')}
                    className="w-full accent-terracotta"
                />
                <div className="mt-2 flex justify-between text-[11px] text-souk-brown-deep/50">
                    <span>0</span>
                    <span>{money(priceCeiling)} MAD</span>
                </div>
            </div>

            <div className="h-px bg-souk-brown/10" />

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
                {t('buyer.products.in_stock_only')}
            </button>

            {hasActiveFilters && (
                <button
                    onClick={resetAll}
                    className="w-full rounded-full border border-souk-brown/20 px-4 py-2 text-sm text-souk-brown-deep transition-colors hover:border-souk-brown hover:bg-souk-brown hover:text-souk-cream"
                >
                    {t('buyer.products.reset_all')}
                </button>
            )}
        </div>
    );

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/* ── Hero: a light, inset card. The carousel is the card's
                background and the copy sits over its start edge, where the
                scrim keeps dark type legible over any of the photographs. */}
            <section className="container pt-6 pb-2 md:pt-8">
                <HeroCarousel
                    slides={HERO_SLIDES.map((slide) => ({
                        src: slide.src,
                        alt: t(slide.altKey),
                    }))}
                >
                    <div className="flex min-h-[clamp(22rem,52vh,30rem)] items-center px-7 py-12 sm:px-12 md:px-16">
                        <div className="max-w-lg">
                            <h1 className="font-display text-[clamp(2.15rem,4.4vw,3.5rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                                <span className="block">
                                    {t('buyer.products.hero_title_l1')}
                                </span>
                                <span className="block">
                                    {t('buyer.products.hero_title_l2')}
                                </span>
                                <span className="block text-terracotta">
                                    {t('buyer.products.hero_title_l3')}
                                </span>
                            </h1>

                            <div
                                aria-hidden="true"
                                className="mt-7 flex items-center gap-2"
                            >
                                <ZellijStarIcon className="h-3 w-3 shrink-0 text-terracotta/70" />
                                <span className="h-px w-8 bg-terracotta/45" />
                                <ZellijStarIcon className="h-3 w-3 shrink-0 text-terracotta/70" />
                                <span className="h-px flex-1 bg-souk-brown/20" />
                            </div>

                            <p className="mt-6 max-w-sm text-[15px] leading-relaxed text-souk-brown-deep/85">
                                {t('buyer.products.hero_subtitle')}
                            </p>

                            <ul className="mt-9 flex flex-wrap gap-x-8 gap-y-6">
                                {HERO_FEATURES.map((feature) => {
                                    const Icon = feature.icon;

                                    return (
                                        <li
                                            key={feature.labelKey}
                                            className="w-[5.5rem]"
                                        >
                                            <Icon
                                                className="h-6 w-6 text-souk-brown"
                                                strokeWidth={1.5}
                                            />
                                            <p className="mt-2.5 text-[13px] leading-tight font-medium text-souk-title">
                                                {t(feature.labelKey)}
                                            </p>
                                            <p className="text-[13px] leading-tight text-souk-brown-deep/70">
                                                {t(feature.subKey)}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </div>
                </HeroCarousel>
            </section>

            {/* ── Category strip ───────────────────────────────────────
                A browse row rather than a filter chip set: one glyph per
                top-level craft, hairline-separated. Scrolls sideways below
                md, where eight of them can't fit without shrinking to
                nothing. Selecting the active one clears it again, which is
                why there is no separate "all" entry. */}
            <nav className="border-b border-souk-brown/10 bg-souk-peach/40">
                <div className="container">
                    <ul className="-mx-5 flex snap-x snap-mandatory overflow-x-auto px-5 md:mx-0 md:justify-center md:overflow-visible md:px-0 [&::-webkit-scrollbar]:hidden">
                        {categories.map((category, i) => {
                            const Icon =
                                CATEGORY_ICONS[category.slug] ?? ZellijStarIcon;
                            const value = String(category.id);
                            const active = filters.category_id === value;

                            return (
                                <li
                                    key={category.id}
                                    className={`shrink-0 snap-start ${
                                        // Hairline between items, never before
                                        // the first — `border-s` so it lands on
                                        // the correct side under RTL.
                                        i > 0
                                            ? 'border-s border-souk-brown/15'
                                            : ''
                                    }`}
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            applyFilters({
                                                category_id: active
                                                    ? ''
                                                    : value,
                                            })
                                        }
                                        aria-pressed={active}
                                        className={`group flex w-[8.5rem] cursor-pointer flex-col items-center gap-2.5 px-3 py-6 transition-colors md:w-[9.5rem] ${
                                            active
                                                ? 'text-souk-title'
                                                : 'text-souk-brown-deep/75 hover:text-souk-title'
                                        }`}
                                    >
                                        <Icon
                                            className={`h-11 w-11 transition-colors ${
                                                active
                                                    ? 'text-terracotta'
                                                    : 'text-souk-brown/70 group-hover:text-terracotta'
                                            }`}
                                        />
                                        <span className="text-center text-[13px] leading-tight">
                                            {category.translated_name}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </nav>

            {/* ── Search, pinned so it stays reachable while scrolling ── */}
            <div className="sticky top-0 z-30 border-b border-souk-brown/10 bg-souk-paper/90 backdrop-blur">
                <div className="container py-4">
                    <div className="relative w-full max-w-md">
                        {busy ? (
                            <Loader2 className="absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-souk-brown/60" />
                        ) : (
                            <Search
                                className="absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-souk-brown/50"
                                strokeWidth={1.5}
                            />
                        )}
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('buyer.products.search_placeholder')}
                            aria-label={t('buyer.products.search_label')}
                            className="w-full rounded-full border border-souk-brown/15 bg-souk-peach/50 py-2.5 ps-11 pe-10 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/45 focus:border-souk-brown/45 focus:bg-souk-peach focus:outline-none"
                        />
                        {search && (
                            <button
                                onClick={() => setSearch('')}
                                aria-label={t('buyer.products.search_clear')}
                                className="absolute end-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-souk-brown/50 transition-colors hover:bg-souk-brown/10 hover:text-souk-title"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Audience notice ─────────────────────────────── */}
            <div className="container pt-8">
                {audience === 'professional' ? (
                    <div className="flex items-start gap-3 rounded-2xl border border-olive/25 bg-olive/5 px-5 py-4 text-sm">
                        <BadgePercent
                            className="mt-0.5 h-4 w-4 shrink-0 text-olive"
                            strokeWidth={1.5}
                        />
                        <p className="text-souk-brown-deep/80">
                            <span className="font-medium text-souk-title">
                                {t('buyer.products.pro_notice_title')}
                            </span>{' '}
                            {t('buyer.products.pro_notice_body')}
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-souk-brown/12 bg-souk-peach/50 px-5 py-4 text-sm">
                        <p className="text-souk-brown-deep/80">
                            {t('buyer.products.pro_cta_body')}
                        </p>
                        <Link
                            href={route('register')}
                            className="shrink-0 rounded-full bg-souk-brown px-5 py-2 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.products.pro_cta_button')}
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
                                {t('buyer.products.refine')}
                            </span>
                            <span className="text-xs text-souk-brown-deep/50">
                                {t('buyer.products.refine_open')}
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
                            {t('buyer.products.refine')}
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
                                    ? t('buyer.products.empty_with_search')
                                    : t('buyer.products.empty')}
                            </p>
                            {hasActiveFilters && (
                                <button
                                    onClick={resetAll}
                                    className="rounded-full bg-souk-brown px-6 py-2.5 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                                >
                                    {t('buyer.products.reset_all')}
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
                                    money={money}
                                    t={t}
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
                                        ? t('buyer.products.loading')
                                        : t('buyer.products.load_more')}
                                </button>
                            )}
                            <p className={`text-souk-brown-deep/50 ${EYEBROW}`}>
                                {products.length} {t('buyer.products.count_of')}{' '}
                                {pagination.total}
                            </p>
                        </div>
                    )}
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
