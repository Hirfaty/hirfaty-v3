import { Form, Link, usePage } from '@inertiajs/react';
import { Hammer, RotateCcw } from 'lucide-react';
import ContactInput from '@/components/contact-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { sell } from '@/routes';
import { details, identify, verify } from '@/routes/login';
import AuthShell from './AuthShell';
import CraftCardStack from './CraftCardStack';
import SocialButtons from './SocialButtons';
import type { SocialProvider } from './SocialButtons';

/*
 * The one field on this screen gets a boxed treatment rather than the house
 * underline: here the field is the entire screen, and a bare underline
 * floating in a large empty card reads as decoration rather than something to
 * type in.
 */
const fieldBoxClass =
    'rounded-lg border border-souk-brown/30 bg-white px-4 transition-colors focus-within:border-souk-brown';

/**
 * The front door — one "email or phone" field, for signing in *and* up.
 *
 * Whatever is typed gets a 6-digit code (Auth\LoginIdentifyController): by
 * email or SMS, to an account that exists or to make one. No password —
 * only admins keep one, and the server sends them to the password screen.
 * There is no "create an account" link because this *is* the
 * create-an-account link.
 *
 * `step={null}`: at this point we do not yet know whether the visitor is
 * signing in or signing up.
 */
export default function LoginIdentify({
    resume,
    socialProviders = [],
}: {
    /**
     * A signup already in progress in this browser, if any. Server-resolved
     * from the resume cookie — see RegistrationCodeService::resolve.
     */
    resume: { maskedContact: string; step: 'verify' | 'details' } | null;
    /**
     * Which of Google/Facebook have credentials configured. Server-decided so
     * the screen cannot offer a button whose route would 404; defaults to none
     * so a partial reload that drops the prop hides them rather than throwing.
     */
    socialProviders?: SocialProvider[];
}) {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { error?: string } }>().props;

    return (
        <AuthShell
            step={null}
            title={t('common.auth.login.identify_title')}
            titleAccent={t('common.auth.login.identify_title_accent')}
            subtitle={t('common.auth.login.identify_subtitle')}
            aside={<CraftCardStack />}
            footer={
                /*
                 * One door, not two. Hirfaty Business used to sit beside this
                 * as a second card, but it is a holding page with no flow
                 * behind it — giving a dead end equal billing with a working
                 * signup made the shelf look twice as full as it is.
                 *
                 * "Hirfaty" stays Latin (it is the brand, and the header
                 * renders it that way in every locale) while the word after it
                 * is translated, so the card reads as the product name in the
                 * visitor's own language rather than as English furniture.
                 */
                <div className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 px-6 py-6 text-center">
                    <p className="font-display text-xl text-souk-title">
                        Hirfaty{' '}
                        <span className="text-souk-brown">
                            {t('common.auth.login.card_seller_brand')}
                        </span>
                    </p>
                    <p className="mt-1.5 text-sm text-souk-brown-deep/70">
                        {t('common.auth.login.card_seller_body')}
                    </p>
                    <Link
                        href={sell()}
                        className="group mt-4 inline-flex items-center gap-2 rounded-full border border-souk-brown/35 px-5 py-2.5 text-sm text-souk-title transition-colors hover:border-souk-brown hover:bg-souk-brown/5"
                    >
                        <Hammer
                            className="h-4 w-4 text-souk-brown"
                            strokeWidth={1.5}
                        />
                        {t('common.auth.login.card_seller_cta')}
                    </Link>
                </div>
            }
        >
            {/* Dead ends elsewhere in the flow land back here with a reason —
                an expired signup, or a session lost between two screens. */}
            {flash?.error && (
                <p
                    role="status"
                    className="mt-6 rounded-xl border border-souk-brown/20 bg-souk-peach px-4 py-3 text-sm text-souk-title"
                >
                    {flash.error}
                </p>
            )}

            {/*
             * Offered, not forced. Someone who comes back here may well want to
             * sign up with a *different* address than the one they abandoned —
             * silently redirecting them to the code screen would trap them
             * with no way to change their mind.
             */}
            {resume && (
                <div className="mt-6 rounded-xl border border-souk-brown/20 bg-souk-brown/5 px-4 py-3.5">
                    <p className="text-sm text-souk-title">
                        {t('common.auth.login.resume_title')}
                    </p>
                    <p className="mt-0.5 text-xs text-souk-brown-deep/65">
                        {t('common.auth.login.resume_body').replace(
                            ':email',
                            resume.maskedContact,
                        )}
                    </p>
                    <Link
                        href={resume.step === 'details' ? details() : verify()}
                        className="group mt-2 inline-flex items-center gap-2 text-sm text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                    >
                        <RotateCcw className="h-3.5 w-3.5" strokeWidth={1.5} />
                        {t('common.auth.login.resume_cta')}
                    </Link>
                </div>
            )}

            <Form {...identify.form()} disableWhileProcessing className="mt-7">
                {({ processing, errors }) => (
                    <>
                        <div>
                            <label
                                htmlFor="identifier"
                                className="mb-1.5 block text-sm font-medium tracking-normal text-souk-title normal-case"
                            >
                                {t('common.auth.login.email_required')}
                            </label>
                            <ContactInput
                                id="identifier"
                                name="identifier"
                                autoFocus
                                error={errors.identifier}
                                boxClassName={fieldBoxClass}
                            />
                        </div>

                        <p className="mt-3 text-xs leading-relaxed text-souk-brown-deep/55">
                            {t('common.auth.login.privacy_note')}
                        </p>

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="login-identify-button"
                            className="mt-5 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {t('common.auth.login.identify_submit')}
                        </button>

                        {/*
                         * No password, no form after the code: a new
                         * shopper's account is made the moment the code is
                         * accepted, so this is where the terms are agreed to.
                         */}
                        <p className="mt-4 text-[11px] leading-relaxed text-souk-brown-deep/55">
                            {t('common.auth.login.terms_continue')}{' '}
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
                        </p>

                        {/* Loi 09-08: required under the sign-up form. */}
                        <p className="mt-2 text-[11px] leading-relaxed text-souk-brown-deep/45">
                            {t('common.auth.login.cndp_notice')}
                        </p>
                    </>
                )}
            </Form>

            {/* Outside the <Form>, deliberately: these are links out to the
                provider, not submissions, and nesting them would let a stray
                Enter keypress send the email form instead of following them. */}
            <SocialButtons providers={socialProviders} />
        </AuthShell>
    );
}
