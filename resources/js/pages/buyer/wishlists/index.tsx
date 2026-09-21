import CollectionCard from '@/components/buyer/CollectionCard';
import EmptyShowcase from '@/components/buyer/EmptyShowcase';
import type { Audience, ListingProduct } from '@/components/buyer/ProductCard';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

/** Maps the app locale onto a BCP-47 tag for Intl number formatting. */

/**
 * The saved-pieces list.
 *
 * Renders the same CollectionCard grid as every other buyer-facing listing —
 * a piece on the wishlist is the same product the catalogue shows, so it gets
 * the same tile. Only when the list is actually empty does the invitation card
 * appear (an empty wishlist and an empty order history are the same moment
 * from the buyer's side: a working section with nothing in it yet, and a
 * reason to go and browse).
 *
 * Every row here is wishlisted by definition, so the hearts all start filled;
 * un-filling one posts the usual toggle and the server's next visit drops the
 * row from the grid.
 */
export default function BuyerWishlists({
    products,
    audience,
    canWishlist,
}: {
    products: ListingProduct[];
    audience: Audience;
    canWishlist: boolean;
}) {
    const { t } = useTranslation();

    /*
     * Prices arrive from the server in MAD. `money()` renders them in the
     * currency this visitor is reading (their own pick, or their IP's) and
     * appends the currency itself — so nothing here should add a suffix.
     */
    const { money } = useCurrency();

    return (
        <BuyerAccountLayout
            active="wishlist"
            title={t('buyer.wishlist.title')}
            description={t('buyer.account.wishlist_intro')}
        >
            {products.length === 0 ? (
                <EmptyShowcase
                    eyebrow={t('buyer.wishlist.empty_eyebrow')}
                    title={t('buyer.wishlist.empty_title')}
                    ctaLabel={t('buyer.wishlist.empty_cta')}
                />
            ) : (
                <div className="grid grid-cols-2 gap-x-3 gap-y-10 sm:gap-x-6 sm:gap-y-14 md:grid-cols-3">
                    {products.map((product) => (
                        <CollectionCard
                            key={product.id}
                            product={product}
                            audience={audience}
                            money={money}
                            t={t}
                            wishlisted
                            canWishlist={canWishlist}
                        />
                    ))}
                </div>
            )}
        </BuyerAccountLayout>
    );
}
