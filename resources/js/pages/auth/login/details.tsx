import { Form, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter';
import { Spinner } from '@/components/ui/spinner';
import { usePasswordCheck } from '@/hooks/use-password-check';
import { useTranslation } from '@/hooks/use-translation';
import { store as createAccount } from '@/routes/login/details';
import AuthShell from './AuthShell';

const fieldClass =
    'border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown';

const labelClass =
    'mb-1 block text-xs tracking-normal text-souk-brown-deep/60 normal-case';

/**
 * Step 3: the last of it — a password, nothing else.
 *
 * Used to also ask for a name and a delivery phone number here. Both are
 * nullable on `users` and neither gates anything a fresh buyer needs to do
 * next — the point of this step is to get them into the marketplace as fast
 * as possible, not to collect a profile up front. Buyer\ProfileController on
 * /account is where a name and phone get filled in whenever the buyer gets
 * around to it, and checkout asks for its own delivery contact regardless.
 *
 * No email field either: the address is settled by now, and showing it as an
 * editable input would imply it can be changed here, which would throw away
 * the code they just typed. The server already knows it from the pending
 * row/cookie — see RegistrationController::showDetails.
 *
 * Back to the default width and thumbnail strip (no `wide`, no
 * `thumbnails={false}`): with two password fields this is a short screen
 * again, the same shape as every other step in the flow.
 */
export default function LoginDetails() {
    const { t } = useTranslation();

    /*
     * Read purely for the strength meter — the field itself stays uncontrolled
     * (no `value` prop on PasswordInput), so this never fights Inertia's
     * <Form>, which reads the live DOM value on submit anyway.
     */
    const [passwordValue, setPasswordValue] = useState('');
    const passwordCheck = usePasswordCheck();

    return (
        <AuthShell
            step={null}
            title={t('common.auth.login.details_title')}
            titleAccent={t('common.auth.login.details_title_accent')}
            subtitle={t('common.auth.login.details_subtitle')}
        >
            <Form
                {...createAccount.form()}
                onBefore={passwordCheck.onBefore}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="mt-7"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-3.5">
                            <div>
                                <label
                                    htmlFor="password"
                                    className={labelClass}
                                >
                                    {t('common.auth.login.password')}
                                </label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    className={fieldClass}
                                    onChange={(e) => {
                                        setPasswordValue(e.target.value);
                                        passwordCheck.clearError('password');
                                    }}
                                />
                                <PasswordStrengthMeter
                                    password={passwordValue}
                                />
                                <InputError
                                    message={
                                        passwordCheck.errors.password ??
                                        errors.password
                                    }
                                    className="mt-1.5"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password_confirmation"
                                    className={labelClass}
                                >
                                    {t('common.auth.login.password_confirm')}
                                </label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    className={fieldClass}
                                    onChange={() =>
                                        passwordCheck.clearError(
                                            'password_confirmation',
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        passwordCheck.errors
                                            .password_confirmation ??
                                        errors.password_confirmation
                                    }
                                    className="mt-1.5"
                                />
                            </div>
                        </div>

                        {/* Password::defaults() enforces this everywhere now,
                            so spell the rules out rather than letting people
                            discover them by failing. */}
                        <p className="mt-1.5 text-[11px] leading-relaxed text-souk-brown-deep/45">
                            {t('common.auth.login.password_hint')}
                        </p>

                        <label className="mt-4 mb-0 flex cursor-pointer items-start gap-2.5 text-sm tracking-normal text-souk-brown-deep/75 normal-case select-none">
                            <input
                                type="checkbox"
                                name="terms_accepted"
                                required
                                className="mt-0.5 h-4 w-4 shrink-0 accent-souk-brown"
                            />
                            <span className="leading-snug">
                                {t('common.auth.login.terms_prefix')}{' '}
                                <Link
                                    href={route('privacy')}
                                    className="text-souk-brown underline underline-offset-4"
                                >
                                    {t('common.auth.login.terms_privacy')}
                                </Link>{' '}
                                {t('common.auth.login.terms_and')}{' '}
                                <Link
                                    href={route('terms')}
                                    className="text-souk-brown underline underline-offset-4"
                                >
                                    {t('common.auth.login.terms_conditions')}
                                </Link>
                                .
                            </span>
                        </label>
                        <InputError
                            message={errors.terms_accepted}
                            className="mt-1.5"
                        />

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="register-details-button"
                            className="group mt-5 inline-flex w-full items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {t('common.auth.login.details_submit')}
                            {!processing && (
                                <ArrowRight
                                    className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180"
                                    strokeWidth={1.75}
                                />
                            )}
                        </button>
                    </>
                )}
            </Form>
        </AuthShell>
    );
}
