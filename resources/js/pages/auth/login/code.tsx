import { Link, usePoll } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { password } from '@/routes/login';
import {
    send as sendLoginCode,
    verify as verifyCode,
} from '@/routes/login/code';
import AuthShell from './AuthShell';
import CodeEntry from './CodeEntry';

/**
 * Passwordless sign-in: the code that gets an existing account in without its
 * password.
 *
 * The sibling screen, verify.tsx, also asks for a code — but that one gates
 * *creating* an account. Passing this one opens a session, which is why the
 * server re-checks eligibility on every action here (no two-factor, not
 * banned; see LoginCodeService::isEligible) rather than trusting that the
 * password screen only offered the option to eligible accounts.
 *
 * `step={null}`: this is a sign-in screen, not one of signup's three stages.
 */
export default function LoginCode({
    maskedContact,
    channel,
    resendIn,
    locked,
    canUsePassword,
}: {
    maskedContact: string;
    /** Whether the code went by email or SMS. */
    channel: 'email' | 'phone';
    /**
     * Only an account that has a password (and an address to sign in with)
     * is offered it — most shoppers never set one.
     */
    canUsePassword: boolean;
    /** Seconds left on the resend cooldown when the page was rendered. */
    resendIn: number;
    /** The current code has taken all the wrong guesses it will take. */
    locked: boolean;
}) {
    const { t } = useTranslation();

    /*
     * The email also has a "sign me in" button. Pressed in another tab or on
     * a phone, it is noticed here within a few seconds and this screen signs
     * in by itself.
     */
    usePoll(3000, {}, { keepAlive: false, autoStart: channel === 'email' });

    return (
        <AuthShell
            step={null}
            title={t('common.auth.login.code_title')}
            titleAccent={t(
                channel === 'phone'
                    ? 'common.auth.login.title_accent_sms'
                    : 'common.auth.login.code_title_accent',
            )}
            subtitle={t(
                channel === 'phone'
                    ? 'common.auth.login.code_subtitle_sms'
                    : 'common.auth.login.code_subtitle',
            ).replace(':email', maskedContact)}
            footer={
                <p className="flex flex-col items-center gap-2 text-center text-sm text-souk-brown-deep/65">
                    {canUsePassword && (
                        <Link
                            href={password()}
                            className="group inline-flex items-center gap-2 text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                        >
                            <ArrowLeft
                                className="h-3.5 w-3.5 rtl:rotate-180"
                                strokeWidth={1.5}
                            />
                            {t('common.auth.login.use_password_instead')}
                        </Link>
                    )}
                    <Link
                        href={login()}
                        className="text-souk-brown/80 underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                    >
                        {t('common.auth.login.wrong_contact')}
                    </Link>
                </p>
            }
        >
            <CodeEntry
                submit={verifyCode.form()}
                // Re-requesting is the same endpoint that sent the first one;
                // its cooldown makes a second press a no-op rather than a
                // second email.
                resendUrl={sendLoginCode.url()}
                resendIn={resendIn}
                locked={locked}
                submitLabel={t('common.auth.login.code_submit')}
                testId="login-code-button"
                channel={channel}
                withLink
            />
        </AuthShell>
    );
}
