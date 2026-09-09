import { Link, router } from '@inertiajs/react';
import { Loader2, MapPin, Package, Store as StoreIcon } from 'lucide-react';
import { useState } from 'react';
import { CollectionCard } from '@/components/buyer/CollectionCard';
import type { Audience, ListingProduct } from '@/components/buyer/ProductCard';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

type StoreType = 'artist' | 'company' | 'cooperative';

type Shop = {
    id: number;
    slug: string;
    store_name: string;
    description: string | null;
    type: StoreType;
    logo: string | null;
    banner: string | null;
    city_name: string | null;
    category_name: string | null;
    created_at: string | null;
};

type Props = {
    store: Shop;
    products: ListingProduct[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        has_more: boolean;
    };
    audience: Audience;
    /** Product ids the signed-in buyer has already wishlisted. */
    wishlisted: number[];
    /** False for guests and non-buyers, whom the wishlist route rejects. */
    canWishlist: boolean;
};

export default function StoreShow({
    store,
    products,
    pagination,
    audience,
    wishlisted,
    canWishlist,
}: Props) {
    const { t, locale } = useTranslation();
    const [loadingMore, setLoadingMore] = useState(false);

    const wished = new Set(wishlisted);

    const tag = LOCALE_TAGS[locale as string] ?? 'fr-MA';

    const money = (value: number | string) =>
        new Intl.NumberFormat(tag, { maximumFractionDigits: 0 }).format(
            Number(value),
        );

    const memberSince = store.created_at
        ? new Intl.DateTimeFormat(tag, {
              month: 'long',
              year: 'numeric',
          }).format(new Date(store.created_at))
        : null;

    /**
     * Appends the next page rather than replacing the grid — the controller
     * sends `products` through Inertia::merge, so only the new rows arrive.
     */
    const loadMore = () => {
        setLoadingMore(true);
        router.reload({
            data: { page: pagination.current_page + 1 },
            only: ['products', 'pagination'],
            onFinish: () => setLoadingMore(false),
        });
    };

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/* ── Shop head ─────────────────────────────────── */}
            <header className="relative mt-24">
                {/*
                 * Cover strip. Falls back to the zellij motif rather than a
                 * flat block, so a shop that never uploaded one still reads as
                 * part of the marketplace rather than as a broken image.
                 */}
                {/* <div className="relative h-44 overflow-hidden bg-souk-brown/10 sm:h-60 lg:h-72">
                    {store.banner ? (
                        <img
                            src={store.banner}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div
                            aria-hidden
                            className="h-full w-full motif-zellij"
                            style={
                                {
                                    '--pattern-color':
                                        'rgba(130, 71, 42, 0.10)',
                                    '--pattern-size': '46px',
                                } as React.CSSProperties
                            }
                        />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-souk-paper/90 to-transparent" />
                </div> */}

                <div className="container">
                    <div className="-mt-14 flex flex-col gap-5 sm:-mt-16 sm:flex-row sm:items-end">
                        {/*
                         * Arch-topped frame, the same horseshoe silhouette the
                         * header mark and product tiles use.
                         */}
                        <div className="h-28 w-28 shrink-0 overflow-hidden rounded-t-[3rem] rounded-b-2xl bg-souk-peach ring-1 ring-souk-brown/15 sm:h-48 sm:w-48">
                            {store.logo ? (
                                <img
                                    src={store.logo}
                                    alt={store.store_name}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center text-souk-brown/35">
                                    <StoreIcon
                                        className="h-9 w-9"
                                        strokeWidth={1.3}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="min-w-0 flex-1 pb-1">
                            <p className={`text-souk-brown ${EYEBROW}`}>
                                {t(`buyer.store.type_${store.type}`)}
                                {store.category_name &&
                                    ` · ${store.category_name}`}
                            </p>

                            <h1 className="mt-2 font-display text-[clamp(1.8rem,3.2vw,2.6rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                                {store.store_name}
                            </h1>

                            <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-souk-brown-deep/70">
                                {store.city_name && (
                                    <span className="flex items-center gap-1.5">
                                        <MapPin
                                            className="h-3.5 w-3.5"
                                            strokeWidth={1.6}
                                        />
                                        {store.city_name}
                                    </span>
                                )}
                                <span className="flex items-center gap-1.5">
                                    <Package
                                        className="h-3.5 w-3.5"
                                        strokeWidth={1.6}
                                    />
                                    {(pagination.total > 1
                                        ? t('buyer.store.count_many')
                                        : t('buyer.store.count_one')
                                    ).replace(
                                        ':count',
                                        String(pagination.total),
                                    )}
                                </span>
                                {memberSince && (
                                    <span>
                                        {t('buyer.store.since').replace(
                                            ':date',
                                            memberSince,
                                        )}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {store.description && (
                        <p className="mt-6 max-w-2xl text-[15px] leading-relaxed text-souk-brown-deep/80">
                            {store.description}
                        </p>
                    )}

                    <div className="mt-8 h-px bg-souk-brown/12" />
                </div>
            </header>

            {/* ── Their pieces ──────────────────────────────── */}
            <section className="container py-10">
                <h2 className="font-display text-2xl text-souk-title">
                    {t('buyer.store.creations')}
                </h2>

                {products.length === 0 ? (
                    <div className="mt-8 rounded-2xl border border-souk-brown/12 bg-souk-peach/40 px-6 py-16 text-center">
                        <ZellijStarIcon className="mx-auto h-9 w-9 text-souk-brown/30" />
                        <p className="mt-5 font-display text-xl text-souk-title">
                            {t('buyer.store.empty_title')}
                        </p>
                        <p className="mx-auto mt-2 max-w-sm text-[15px] leading-relaxed text-souk-brown-deep/70">
                            {t('buyer.store.empty_body')}
                        </p>
                        <Link
                            href={route('products.index')}
                            className="mt-7 inline-flex items-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.store.empty_cta')}
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Same tile and the same grid rhythm as the
                            marketplace listing — see
                            `pages/buyer/products/index.tsx` — so a shop's own
                            page reads as a filtered view of the same
                            catalogue rather than a different product. */}
                        <div className="mt-8 grid grid-cols-2 gap-x-3 gap-y-10 sm:gap-x-6 sm:gap-y-14 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-7">
                            {products.map((product) => (
                                <CollectionCard
                                    key={product.id}
                                    product={product}
                                    audience={audience}
                                    money={money}
                                    t={t}
                                    wishlisted={wished.has(product.id)}
                                    canWishlist={canWishlist}
                                />
                            ))}
                        </div>

                        {pagination.has_more && (
                            <div className="mt-12 text-center">
                                <button
                                    type="button"
                                    onClick={loadMore}
                                    disabled={loadingMore}
                                    className="inline-flex items-center gap-2.5 rounded-xl border border-souk-brown/25 px-7 py-3.5 text-sm font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown disabled:opacity-50"
                                >
                                    {loadingMore ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            {t('buyer.store.loading')}
                                        </>
                                    ) : (
                                        t('buyer.store.load_more')
                                    )}
                                </button>
                            </div>
                        )}
                    </>
                )}
            </section>

            <SiteFooter />
        </div>
    );
}
