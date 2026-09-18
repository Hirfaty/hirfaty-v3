import { Form, Link, usePage } from '@inertiajs/react';
import { Hammer, RotateCcw } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { sell } from '@/routes';
import { details, identify, verify } from '@/routes/login';
import AuthShell from './AuthShell';
import CraftCardStack from './CraftCardStack';
import SocialButtons, { type SocialProvider } from './SocialButtons';

/*
 * The one field on this screen gets a boxed treatment rather than the house
 * underline. `Input` is underline-only by default (`border-0 border-b`) and
 * stays that way everywhere else; here the field is the entire screen, and a
 * bare underline floating in a large empty card reads as decoration rather
 * than something to type in. `border-b-0` wins over the component's own
 * `border-b` because Input merges through tailwind-merge, and the visible
 * border moves to the wrapper so the focus ring can sit on the whole box.
 */
const fieldClass = 'border-b-0 pe-0 text-base text-souk-title';

const fieldBoxClass =
    'rounded-lg border border-souk-brown/30 bg-white px-4 transition-colors focus-within:border-souk-brown';

/**
 * The front door — one email field, for signing in *and* signing up.
 *
 * What comes next is decided server-side by
 * Auth\LoginIdentifyController::identify: a password screen if the address has
 * an account, an emailed code if it doesn't. That is why this screen asks
 * nothing beyond the address, and why there is no "create an account" link —
 * this *is* the create-an-account link.
 *
 * The step indicator is suppressed (`step={null}`) even though signup does
 * have three stages: at this point we do not yet know whether the visitor is
 * signing in or signing up, so "step 1 of 3" would promise two more screens
 * that a returning visitor never sees.
 */
export default function LoginIdentify({
    resume,
    socialProviders = [],
}: {
    /**
     * A signup already in progress in this browser, if any. Server-resolved
     * from the resume cookie — see RegistrationCodeService::resolve.
     */
    resume: { maskedEmail: string; step: 'verify' | 'details' } | null;
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
                            resume.maskedEmail,
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
                                htmlFor="email"
                                className="mb-1.5 block text-sm font-medium tracking-normal text-souk-title normal-case"
                            >
                                {t('common.auth.login.email_required')}
                            </label>
                            <div className={fieldBoxClass}>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    className={fieldClass}
                                />
                            </div>
                            <InputError
                                message={errors.email}
                                className="mt-1.5"
                            />
                        </div>

                        {/* Plain text, not a link: there is no /privacy route
                            in this app yet, and a 404 undercuts the exact
                            reassurance the sentence is there to give. */}
                        <p className="mt-4 text-xs leading-relaxed text-souk-brown-deep/55">
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
