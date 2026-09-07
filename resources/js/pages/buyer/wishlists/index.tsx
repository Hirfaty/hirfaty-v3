import { Heart } from 'lucide-react';
import Placeholder from '@/components/buyer/account/Placeholder';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

export default function BuyerWishlists() {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="wishlist"
            title={t('buyer.wishlist.title')}
            description={t('buyer.account.wishlist_intro')}
        >
            <Placeholder icon={Heart} />
        </BuyerAccountLayout>
    );
}
