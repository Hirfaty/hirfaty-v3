import { BadgeCheck } from 'lucide-react';
import Placeholder from '@/components/buyer/account/Placeholder';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';

/**
 * Where a buyer manages trade access — asking for it, and seeing what
 * happened to a request already sent.
 *
 * The controller (Buyer\ProfessionalRequestController) still resolves and
 * ships the real state — status, canRequest, the decline reason — so this
 * page can be filled back in without touching the backend. It renders a
 * placeholder for now, matching the rest of the account area.
 */
export default function ProfessionalAccount() {
    const { t } = useTranslation();

    return (
        <BuyerAccountLayout
            active="professional_account"
            title={t('buyer.account.nav_professional')}
            description={t('buyer.account.professional_intro')}
        >
            <Placeholder icon={BadgeCheck} />
        </BuyerAccountLayout>
    );
}
