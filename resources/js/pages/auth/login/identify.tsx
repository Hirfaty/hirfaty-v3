import { Form, Link, usePage } from '@inertiajs/react';
import { Briefcase, Hammer, RotateCcw } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { business, sell } from '@/routes';
import { details, identify, verify } from '@/routes/login';
import AuthShell from './AuthShell';
import CraftCardStack from './CraftCardStack';

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
 * The two doors that are not "I am a shopper".
 *
 * `brand` is hardcoded rather than translated: "Hirfaty Seller" and "Hirfaty
 * Business" are product names, and product names stay in Latin script in
 * every locale. Only the supporting line and the button are translated.
 */
const ACCOUNT_KINDS = [
    { key: 'seller', brand: 'Seller', href: sell, Icon: Hammer },
    { key: 'business', brand: 'Business', href: business, Icon: Briefcase },
] as const;

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
}: {
    /**
     * A signup already in progress in this browser, if any. Server-resolved
     * from the resume cookie — see RegistrationCodeService::resolve.
     */
    resume: { maskedEmail: string; step: 'verify' | 'details' } | null;
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
                <div className="grid gap-4 sm:grid-cols-2">
                    {ACCOUNT_KINDS.map(({ key, brand, href, Icon }) => (
                        <div
                            key={key}
                            className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 px-6 py-6 text-center"
                        >
                            <p className="font-display text-xl text-souk-title">
                                Hirfaty{' '}
                                <span className="text-souk-brown">{brand}</span>
                            </p>
                            <p className="mt-1.5 text-sm text-souk-brown-deep/70">
                                {t(`common.auth.login.card_${key}_body`)}
                            </p>
                            <Link
                                href={href()}
                                className="group mt-4 inline-flex items-center gap-2 rounded-full border border-souk-brown/35 px-5 py-2.5 text-sm text-souk-title transition-colors hover:border-souk-brown hover:bg-souk-brown/5"
                            >
                                <Icon
                                    className="h-4 w-4 text-souk-brown"
                                    strokeWidth={1.5}
                                />
                                {t(`common.auth.login.card_${key}_cta`)}
                            </Link>
                        </div>
                    ))}
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
        </AuthShell>
    );
}
