import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Loader2, PackageSearch } from 'lucide-react';
import { useState } from 'react';
import { CollectionCard } from '@/components/buyer/CollectionCard';
import type { ListingProduct } from '@/components/buyer/ProductCard';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCurrency } from '@/hooks/use-currency';
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
    const { t } = useTranslation();
    const [loadingMore, setLoadingMore] = useState(false);

    const wished = new Set(wishlisted);

    /*
     * Prices arrive from the server in MAD. `money()` renders them in the
     * currency this visitor is reading and appends the currency itself.
     */
    const { money } = useCurrency();

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
