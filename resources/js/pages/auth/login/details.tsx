import { Form, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { store as createAccount } from '@/routes/login/details';
import AuthShell from './AuthShell';

const fieldClass =
    'border-souk-brown/25 py-2.5 text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown';

const labelClass =
    'mb-1 block text-xs tracking-normal text-souk-brown-deep/60 normal-case';

/**
 * Step 3: the last of it — name, a delivery phone number and a password.
 *
 * No email field: the address is settled by now, and showing it as an editable
 * input would imply it can be changed here, which would throw away the code
 * they just typed. The server already knows it from the pending row/cookie,
 * so there is nothing this screen needs the address *for* — see
 * RegistrationController::showDetails, which stopped passing it once the
 * confirmed-email badge that used to display it was removed.
 *
 * `wide` and `thumbnails={false}`: this screen holds five fields rather than
 * one, and reads cramped at the same width as the rest of the flow — and
 * dense enough already without a decorative photo strip pulling the eye
 * further down. `step={null}` drops the 1-2-3 indicator for the same reason
 * the sign-in screens do: it is chrome, not information the visitor needs to
 * fill in a form.
 */
export default function LoginDetails() {
    const { t } = useTranslation();

    /*
     * Read purely for the strength meter — the field itself stays uncontrolled
     * (no `value` prop on PasswordInput), so this never fights Inertia's
     * <Form>, which reads the live DOM value on submit anyway.
     */
    const [passwordValue, setPasswordValue] = useState('');

    return (
        <AuthShell
            step={null}
            wide
            thumbnails={false}
            title={t('common.auth.login.details_title')}
            titleAccent={t('common.auth.login.details_title_accent')}
            subtitle={t('common.auth.login.details_subtitle')}
        >
            <Form
                {...createAccount.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="mt-5"
            >
                {({ processing, errors }) => (
                    <>
                        {/* Single column on a phone — five fields squeezed
                            two-to-a-row is what "should be under each other on
                            mobile" is about. Pairs up from `sm` and wider. */}
                        <div className="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="first_name"
                                    className={labelClass}
                                >
                                    {t('common.auth.login.first_name')}
                                </label>
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="given-name"
                                    className={fieldClass}
                                />
                                <InputError
                                    message={errors.first_name}
                                    className="mt-1.5"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="last_name"
                                    className={labelClass}
                                >
                                    {t('common.auth.login.last_name')}
                                </label>
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    type="text"
                                    required
                                    autoComplete="family-name"
                                    className={fieldClass}
                                />
                                <InputError
                                    message={errors.last_name}
                                    className="mt-1.5"
                                />
                            </div>

                            {/* Phone needs the full width once the grid is
                                two columns — a formatted international number
                                does not fit a half column. Below `sm` every
                                field is already full width, so there is
                                nothing to span. */}
                            <div className="sm:col-span-2">
                                <label
                                    htmlFor="phone_number"
                                    className={labelClass}
                                >
                                    {t('common.auth.login.phone')}
                                </label>
                                <Input
                                    id="phone_number"
                                    name="phone_number"
                                    type="tel"
                                    required
                                    autoComplete="tel"
                                    placeholder="+212 6 12 34 56 78"
                                    className={fieldClass}
                                />
                                <InputError
                                    message={errors.phone_number}
                                    className="mt-1.5"
                                />
                            </div>

                            {/* Masked fields tolerate a narrow column, so they
                                pair up even on a phone. */}
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
                                    autoComplete="new-password"
                                    className={fieldClass}
                                    onChange={(e) =>
                                        setPasswordValue(e.target.value)
                                    }
                                />
                                <PasswordStrengthMeter
                                    password={passwordValue}
                                />
                                <InputError
                                    message={errors.password}
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
                                />
                                <InputError
                                    message={errors.password_confirmation}
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
                                    href="/privacy"
                                    className="text-souk-brown underline underline-offset-4"
                                >
                                    {t('common.auth.login.terms_privacy')}
                                </Link>{' '}
                                {t('common.auth.login.terms_and')}{' '}
                                <Link
                                    href="/terms"
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
