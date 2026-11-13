import { Briefcase } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import AccountKindPlaceholder from './AccountKindPlaceholder';

/**
 * Hirfaty Business — the trade door, not yet built.
 *
 * Pro prices need no account of their own: anyone switches to "Pro" in the
 * header and buys from each piece's minimum quantity (PriceModeService) — see
 * /professional-account. This page stays a placeholder until a separate
 * business signup is actually designed.
 */
export default function JoinBusiness() {
    const { t } = useTranslation();

    return (
        <AccountKindPlaceholder
            brand="Business"
            Icon={Briefcase}
            title={t('common.auth.login.placeholder_business_title')}
            body={t('common.auth.login.placeholder_business_body')}
        />
    );
}
