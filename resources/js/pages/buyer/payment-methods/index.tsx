import { CreditCard } from 'lucide-react';
import Placeholder from '@/components/buyer/account/Placeholder';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

export default function BuyerPaymentMethods() {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="payment_methods"
            title={t('buyer.account.nav_payment')}
            description={t('buyer.account.payment_intro')}
        >
            <Placeholder icon={CreditCard} />
        </BuyerAccountLayout>
    );
}
