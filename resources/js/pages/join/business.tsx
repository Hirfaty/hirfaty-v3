import { Briefcase } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import AccountKindPlaceholder from './AccountKindPlaceholder';

/**
 * Hirfaty Business — the trade door, not yet built.
 *
 * This is where `professional_buyer` signup will live. Worth saying plainly
 * why it is a stub rather than a form: a trade account is billed off the
 * wholesale price list, and the app has always refused to hand that out on a
 * self-declaration. The role is granted only by an admin review
 * (Admin\UserController::approveProfessional), and the one place that creates
 * accounts — RegistrationCodeService::complete — hardcodes `buyer`. So
 * "register as a business" cannot just be a form that writes a role; it needs
 * the vetting step designed alongside it.
 *
 * Buyers who already have an account can request the upgrade today, via
 * /professional-account.
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
