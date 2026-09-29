import { Head, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Signup step 2, reached from the "confirm my email" button in the email —
 * but in a different browser from the one the signup started in (usually the
 * phone the email was read on, or a mail app's built-in browser).
 *
 * One button, on purpose. Mail scanners open every link in an email; if
 * opening the link were enough, a scanner would take the signup over before
 * the person ever saw it. Pressing this is what moves the signup here (see
 * RegistrationController::confirmLink). In the browser that started the
 * signup this page never shows — the link goes straight to the details step.
 *
 * Same bare layout as verify.tsx, for the same reason: it is reached straight
 * from an inbox, often on a phone, with one thing to do.
 */
export default function LoginLink({
    maskedEmail,
    action,
}: {
    maskedEmail: string;
    /** The signed link itself — the POST is checked against its signature. */
    action: string;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.post(action, undefined, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title={t('common.auth.login.page_title')} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-souk-paper px-5 py-10 sm:px-8">
                <div className="w-full max-w-md">
                    <h1 className="font-display text-[clamp(1.5rem,3.2vw,2rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                        {t('common.auth.login.link_title')}{' '}
                        <span className="text-souk-brown">
                            {t('common.auth.login.link_title_accent')}
                        </span>
                    </h1>

                    <p className="mt-2.5 text-sm leading-relaxed text-souk-brown-deep/70">
                        {t('common.auth.login.link_subtitle').replace(
                            ':email',
                            maskedEmail,
                        )}
                    </p>

                    <button
                        type="button"
                        onClick={submit}
                        disabled={processing}
                        data-test="login-link-button"
                        className="group mt-8 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {processing && <Spinner />}
                        {t('common.auth.login.link_submit')}
                        {!processing && (
                            <ArrowRight
                                className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180"
                                strokeWidth={1.75}
                            />
                        )}
                    </button>
                </div>
            </div>
        </>
    );
}
