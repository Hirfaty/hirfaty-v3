import { Hammer } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import AccountKindPlaceholder from './AccountKindPlaceholder';

/**
 * Hirfaty Seller — the artisan door, not yet built.
 *
 * This replaced the original one-page artisan form. That form worked, but it
 * predated the email-code flow and the dedicated seller onboarding that is
 * coming, so keeping it would have meant maintaining a signup path we already
 * know is being replaced.
 *
 * Note there is no longer *any* endpoint that creates a seller: Fortify's
 * `POST /register` and `CreateNewUser` went with the merge into /login (see
 * config/fortify.php). The new flow will need its own, and
 * RegistrationCodeService::complete is the shape to follow — it creates an
 * account only after an emailed code has proven the address.
 */
export default function JoinSeller() {
    const { t } = useTranslation();

    return (
        <AccountKindPlaceholder
            brand="Seller"
            Icon={Hammer}
            title={t('common.auth.login.placeholder_seller_title')}
            body={t('common.auth.login.placeholder_seller_body')}
        />
    );
}
