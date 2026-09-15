import { Form, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { send as sendLoginCode } from '@/routes/login/code';
import { request as forgotPassword } from '@/routes/password';
import AuthShell from './AuthShell';

const fieldClass = 'border-b-0 pe-10 text-base text-souk-title';

const fieldBoxClass =
    'relative rounded-lg border border-souk-brown/30 bg-white px-4 transition-colors focus-within:border-souk-brown';

/**
 * Step 2 of signing in: the password, for an address we have already
 * established has an account.
 *
 * Submits to **Fortify's** `POST /login` (`login.store`), not to anything in
 * Auth\LoginIdentifyController. That is deliberate and load-bearing: Fortify's
 * endpoint carries the `login` rate limiter, session regeneration, the
 * two-factor challenge handoff and LoginResponse's per-role landing.
 * Re-implementing password auth to fit a prettier screen would quietly drop
 * all four.
 *
 * The email travels in a hidden field because Fortify authenticates on
 * `email` + `password` from the request — the session copy
 * (`LoginCodeService::SESSION_KEY`) is what *renders* this page, but Fortify
 * never reads it.
 */
export default function LoginPassword({
    email,
    canUseCode,
}: {
    email: string;
    /**
     * Whether to offer "email me a code instead". False for accounts with
     * two-factor enabled (a mailed code alone would bypass the second factor)
     * and for banned accounts — see LoginCodeService::isEligible. Rendering a
     * button that is guaranteed to fail is worse than not rendering it.
     */
    canUseCode: boolean;
}) {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { error?: string } }>().props;

    return (
        <AuthShell
            step={null}
            title={t('common.auth.login.password_title')}
            titleAccent={t('common.auth.login.password_title_accent')}
            subtitle={t('common.auth.login.password_subtitle')}
            footer={
                <p className="text-center text-sm text-souk-brown-deep/65">
                    <Link
                        href={login()}
                        className="group inline-flex items-center gap-2 text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                    >
                        <ArrowLeft
                            className="h-3.5 w-3.5 rtl:rotate-180"
                            strokeWidth={1.5}
                        />
                        {t('common.auth.login.use_another_email')}
                    </Link>
                </p>
            }
        >
            {flash?.error && (
                <p
                    role="status"
                    className="mt-6 rounded-xl border border-souk-brown/20 bg-souk-peach px-4 py-3 text-sm text-souk-title"
                >
                    {flash.error}
                </p>
            )}

            {/* The address they identified as, shown rather than re-asked —
                the whole point of the previous screen. */}
            <p className="mt-5 inline-flex items-center gap-2 rounded-lg bg-souk-brown/5 px-3 py-2 text-sm text-souk-title">
                <BadgeCheck
                    className="h-4 w-4 shrink-0 text-souk-brown"
                    strokeWidth={1.75}
                />
                <span dir="ltr">{email}</span>
            </p>

            <Form
                action={route('login.store')}
                method="post"
                resetOnSuccess={['password']}
                disableWhileProcessing
                className="mt-5"
            >
                {({ processing, errors }) => (
                    <>
                        {/* Fortify reads the username off the request, not the
                            session, so it has to travel with the form. */}
                        <input
                            type="hidden"
                            name="email"
                            value={email}
                            readOnly
                        />

                        <div>
                            <label
                                htmlFor="password"
                                className="mb-1.5 block text-sm font-medium tracking-normal text-souk-title normal-case"
                            >
                                {t('common.auth.login.password')}
                            </label>
                            <div className={fieldBoxClass}>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="current-password"
                                    className={fieldClass}
                                />
                            </div>
                            <InputError
                                message={errors.password ?? errors.email}
                                className="mt-1.5"
                            />
                        </div>

                        <label className="mt-4 mb-0 flex cursor-pointer items-center gap-2.5 text-sm tracking-normal text-souk-brown-deep/75 normal-case select-none">
                            <input
                                type="checkbox"
                                name="remember"
                                className="h-4 w-4 shrink-0 accent-souk-brown"
                            />
                            {t('common.auth.login.remember')}
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="login-password-button"
                            className="mt-5 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {t('common.auth.login.submit')}
                        </button>
                    </>
                )}
            </Form>

            <div className="mt-5 space-y-2.5 border-t border-souk-brown/10 pt-4 text-center">
                {/*
                 * The passwordless escape hatch. Doubles as a gentler recovery
                 * route than a reset link: someone who cannot remember their
                 * password gets in without having to change it.
                 *
                 * `router.post` rather than a <Link>, because asking for a code
                 * sends mail — that is a side effect, and side effects do not
                 * belong behind a GET.
                 */}
                {canUseCode && (
                    <button
                        type="button"
                        onClick={() => router.post(sendLoginCode.url())}
                        data-test="login-use-code-button"
                        className="group inline-flex items-center gap-2 text-sm text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                    >
                        <KeyRound className="h-3.5 w-3.5" strokeWidth={1.5} />
                        {t('common.auth.login.use_code_instead')}
                    </button>
                )}

                <p>
                    <Link
                        href={forgotPassword()}
                        className="text-xs text-souk-brown-deep/55 underline underline-offset-4 transition-colors hover:text-souk-brown"
                    >
                        {t('common.auth.login.forgot')}
                    </Link>
                </p>
            </div>
        </AuthShell>
    );
}
