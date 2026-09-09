import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Loader2, PackageSearch } from 'lucide-react';
import { useState } from 'react';
import { CollectionCard } from '@/components/buyer/CollectionCard';
import type { ListingProduct } from '@/components/buyer/ProductCard';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    products: ListingProduct[];
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        has_more: boolean;
    };
    audience: 'individual' | 'professional';
    /** Product ids the signed-in buyer has already wishlisted. */
    wishlisted: number[];
    /** False for guests and non-buyers, whom the wishlist route rejects. */
    canWishlist: boolean;
};

/** Museum-caption label, shared with the cards below. */
const EYEBROW = 'text-[10px] font-semibold uppercase tracking-[0.16em]';

/** Maps the app locale onto a BCP-47 tag for Intl number formatting. */
const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/**
 * The newest pieces in the catalogue.
 *
 * Deliberately leaner than the main listing: no search, no category tabs.
 * This is a chronological feed reached from the nav — anyone who wants to
 * narrow it belongs on /products, which is what the footer link offers.
 */
export default function NewArrivals({
    products,
    pagination,
    audience,
    wishlisted,
    canWishlist,
}: Props) {
    const { t, locale } = useTranslation();
    const [loadingMore, setLoadingMore] = useState(false);

    const wished = new Set(wishlisted);

    const money = (value: number | string) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            maximumFractionDigits: 0,
        }).format(Number(value));

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

    return (
        <>
            <Head title={t('buyer.new.title')} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* Same traced arcade as the marketplace hero, at half the
                    height — this is a section of the catalogue, so it should
                    read as belonging to it rather than as a second front
                    door. */}
                <section className="relative isolate overflow-hidden border-b border-souk-brown/15 px-5 py-14 sm:px-8 sm:py-16 lg:px-12">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute top-6 left-1/2 -z-10 h-[115%] w-[min(60vw,620px)] -translate-x-1/2 [border-radius:50%_50%_0_0/28%_28%_0_0] border border-souk-brown/12"
                    />

                    <div className="mx-auto max-w-3xl text-center">
                        <p className={`mb-4 text-terracotta-deep ${EYEBROW}`}>
                            {t('buyer.new.eyebrow')}
                        </p>

                        <h1 className="font-display text-4xl leading-[1.02] text-balance text-souk-brown sm:text-6xl">
                            {t('buyer.new.heading')}
                        </h1>

                        <div
                            aria-hidden="true"
                            className="mx-auto my-6 flex w-32 items-center gap-3"
                        >
                            <span className="h-px flex-1 bg-souk-brown/25" />
                            <span className="size-2 rotate-45 border border-souk-brown/50" />
                            <span className="h-px flex-1 bg-souk-brown/25" />
                        </div>

                        <p className="mx-auto max-w-xl text-sm leading-7 text-balance text-souk-brown-deep/80">
                            {t('buyer.new.lead')}
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-[1440px] px-5 py-14 sm:px-8 sm:py-20 lg:px-12">
                    <div className="mb-7 flex items-center justify-between gap-4 text-xs text-souk-brown-deep/70">
                        <span>
                            {pagination.total}{' '}
                            {t('buyer.products.count_pieces')}
                        </span>
                        <Link
                            href={route('products.index')}
                            className="group inline-flex items-center gap-2 border-b border-souk-brown/40 pb-0.5 text-souk-brown transition-colors hover:border-souk-brown"
                        >
                            {t('buyer.new.browse_all')}
                            <ArrowRight
                                className="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                                strokeWidth={1.75}
                            />
                        </Link>
                    </div>

                    {products.length === 0 ? (
                        <div className="flex flex-col items-center gap-4 border border-dashed border-souk-brown/20 py-24 text-center">
                            <PackageSearch
                                className="h-9 w-9 text-souk-brown/25"
                                strokeWidth={1.5}
                            />
                            <p className="text-souk-brown-deep/70">
                                {t('buyer.new.empty')}
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-x-3 gap-y-10 sm:gap-x-6 sm:gap-y-14 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-7">
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
                                className="inline-flex items-center gap-2 border border-souk-brown px-8 py-3.5 text-sm text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-50"
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

                <SiteFooter />
            </div>
        </>
    );
}
