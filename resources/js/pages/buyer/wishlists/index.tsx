import EmptyShowcase from '@/components/buyer/EmptyShowcase';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

/**
 * The saved-pieces list.
 *
 * Shows the same invitation card as an empty order list (see EmptyShowcase) —
 * an empty wishlist and an empty order history are the same moment from the
 * buyer's side: a working section with nothing in it yet, and a reason to go
 * and browse.
 *
 * Still always-empty: the wishlist rows themselves are not wired up here yet,
 * so there is no `items` prop to branch on. When they are, this becomes the
 * same `length === 0 ? <EmptyShowcase/> : <list/>` shape the orders page uses.
 */
export default function BuyerWishlists() {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="wishlist"
            title={t('buyer.wishlist.title')}
            description={t('buyer.account.wishlist_intro')}
        >
            <EmptyShowcase
                eyebrow={t('buyer.wishlist.empty_eyebrow')}
                title={t('buyer.wishlist.empty_title')}
                ctaLabel={t('buyer.wishlist.empty_cta')}
            />
        </BuyerAccountLayout>
    );
}
