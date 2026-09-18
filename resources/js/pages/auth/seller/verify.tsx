import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import heroWeaver from '@/images/hero/hero-3.jpg';
import { sell } from '@/routes';
import { resend } from '@/routes/login';
import { store as verifyCode } from '@/routes/login/verify';
import CodeEntry from '../login/CodeEntry';
import SellerAuthShell from './SellerAuthShell';

/**
 * Seller signup step 2 — the 6-digit code that proves the address is theirs.
 *
 * The same endpoints as the shopper's code screen (`login.verify`,
 * `login.resend`): one `pending_registrations` row, one attempt ceiling, one
 * cooldown. Only the chrome differs, so an artisan does not watch the page
 * change character half-way through signing up.
 *
 * "Wrong address" points back at /sell rather than /login — restarting here
 * has to keep the seller intent, and bouncing them to the shopper door would
 * quietly turn their signup into a buyer account.
 */
export default function SellerVerify({
    maskedEmail,
    resendIn,
    locked,
}: {
    maskedEmail: string;
    /** Seconds left on the resend cooldown when the page was rendered. */
    resendIn: number;
    /** The current code has taken all the wrong guesses it will take. */
    locked: boolean;
}) {
    const { t } = useTranslation();

    return (
        <SellerAuthShell
            image={heroWeaver}
            quote={t('seller.join.quote_verify')}
            title={t('common.auth.login.verify_title')}
            titleAccent={t('common.auth.login.verify_title_accent')}
            subtitle={t('common.auth.login.verify_subtitle').replace(
                ':email',
                maskedEmail,
            )}
        >
            <CodeEntry
                submit={verifyCode.form()}
                resendUrl={resend.url()}
                resendIn={resendIn}
                locked={locked}
                submitLabel={t('common.auth.login.verify_submit')}
                testId="sell-verify-button"
            />

            <p className="mt-5 text-sm text-souk-brown-deep/65">
                <Link
                    href={sell()}
                    className="text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                >
                    {t('common.auth.login.wrong_email')}
                </Link>
            </p>
        </SellerAuthShell>
    );
}
