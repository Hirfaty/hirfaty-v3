import { Head, Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { resend } from '@/routes/login';
import { store as verifyCode } from '@/routes/login/verify';
import CodeEntry from './CodeEntry';

/**
 * Signup step 2: the 6-digit code that proves a brand-new address is theirs.
 *
 * Deliberately bare — no AuthShell chrome (top bar, logo, step indicator,
 * thumbnail strip). This is the one screen in the flow someone reaches
 * straight from their inbox, often on a phone, with exactly one task: read a
 * code and type it back. Title, subtitle, the code itself, "send a new one"
 * and "wrong address" are the whole of what that task needs; everything else
 * was surface area to get lost in.
 *
 * The sibling screen, code.tsx, asks for a code too — but that one signs an
 * *existing* account in. Same six boxes (see CodeEntry), different meaning:
 * this one is a precondition for creating an account, and passing it leads to
 * profile completion rather than a session.
 */
export default function LoginVerify({
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
        <>
            <Head title={t('common.auth.login.page_title')} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-souk-paper px-5 py-10 sm:px-8">
                <div className="w-full max-w-md">
                    <h1 className="font-display text-[clamp(1.5rem,3.2vw,2rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                        {t('common.auth.login.verify_title')}{' '}
                        <span className="text-souk-brown">
                            {t('common.auth.login.verify_title_accent')}
                        </span>
                    </h1>

                    <p className="mt-2.5 text-sm leading-relaxed text-souk-brown-deep/70">
                        {t('common.auth.login.verify_subtitle').replace(
                            ':email',
                            maskedEmail,
                        )}
                    </p>

                    <CodeEntry
                        submit={verifyCode.form()}
                        resendUrl={resend.url()}
                        resendIn={resendIn}
                        locked={locked}
                        submitLabel={t('common.auth.login.verify_submit')}
                        testId="login-verify-button"
                    />

                    <p className="mt-5 text-center text-sm text-souk-brown-deep/65">
                        <Link
                            href={login()}
                            className="text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                        >
                            {t('common.auth.login.wrong_email')}
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
