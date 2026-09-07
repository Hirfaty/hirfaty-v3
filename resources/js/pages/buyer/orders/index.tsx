import { Package } from 'lucide-react';
import Placeholder from '@/components/buyer/account/Placeholder';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

export default function BuyerOrders() {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="orders"
            title={t('buyer.account.nav_orders')}
            description={t('buyer.account.orders_intro')}
        >
            <Placeholder icon={Package} />
        </BuyerAccountLayout>
    );
}
