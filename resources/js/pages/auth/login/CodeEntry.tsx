import { Form, router, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { ArrowRight, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

const CODE_LENGTH = 6;

/**
 * The six boxes, the submit button and the resend countdown — shared by the
 * two screens in this flow that ask for a 6-digit code: confirming a new
 * address (verify.tsx) and signing in without a password (code.tsx).
 *
 * Extracted rather than copied because the two are identical in every respect
 * a visitor can see, and the parts that are easy to get subtly wrong — the
 * server-anchored countdown, clearing the boxes on a bad code, the disabled
 * states — are exactly the parts that would drift between two copies. Each
 * page still owns its own heading, copy and AuthShell wrapper.
 *
 * Uses the same InputOTP primitive as the two-factor challenge
 * (pages/auth/two-factor-challenge.tsx), so every "type a code" moment in the
 * app looks and behaves the same.
 */
export default function CodeEntry({
    submit,
    resendUrl,
    resendIn,
    locked,
    submitLabel,
    testId,
}: {
    /** Wayfinder form definition to post the code to. */
    submit: { action: string; method: 'post' };
    /** Endpoint that mails a fresh code. */
    resendUrl: string;
    /** Seconds left on the resend cooldown when the page was rendered. */
    resendIn: number;
    /** The current code has taken all the wrong guesses it will take. */
    locked: boolean;
    submitLabel: string;
    testId: string;
}) {
    const { t } = useTranslation();
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    const [code, setCode] = useState('');

    /*
     * Counts down from whatever the server said was left, rather than from a
     * fixed 60 — otherwise coming back to this page mid-cooldown would restart
     * the clock and promise a longer wait than the server will actually
     * enforce.
     *
     * `from` tracks which server value the countdown belongs to, so a resend
     * (which re-renders this page with a fresh `resendIn` but does *not*
     * remount it) restarts the clock. Adjusted during render — React's
     * documented pattern for "a prop changed, derive state again" — because
     * doing the same sync inside an effect cascades an extra render on every
     * resend, which is exactly what react-hooks/set-state-in-effect flags.
     */
    const [cooldown, setCooldown] = useState({
        from: resendIn,
        left: resendIn,
    });

    if (cooldown.from !== resendIn) {
        setCooldown({ from: resendIn, left: resendIn });
    }

    const secondsLeft = cooldown.left;

    useEffect(() => {
        if (secondsLeft <= 0) {
            return;
        }

        const timer = setTimeout(
            () => setCooldown((c) => ({ ...c, left: c.left - 1 })),
            1000,
        );

        return () => clearTimeout(timer);
    }, [secondsLeft]);

    return (
        <>
            {flash?.success && (
                <p
                    role="status"
                    className="mt-6 rounded-xl border border-souk-brown/20 bg-souk-brown/5 px-4 py-3 text-sm text-souk-title"
                >
                    {flash.success}
                </p>
            )}

            <Form
                {...submit}
                // Clears the boxes on a wrong code: leaving six wrong digits in
                // place means the next attempt starts with a delete-six-times
                // chore.
                resetOnError
                onError={() => setCode('')}
                disableWhileProcessing
                className="mt-6"
            >
                {({ processing, errors }) => (
                    <>
                        <label className="mb-2 block text-xs tracking-normal text-souk-brown-deep/60 normal-case">
                            {t('common.auth.login.code_label')}
                        </label>

                        {/* dir="ltr" even in Arabic: the digits are one
                            left-to-right token, and the boxes must fill in the
                            order they are read off the email. */}
                        <div className="flex justify-center" dir="ltr">
                            <InputOTP
                                name="code"
                                maxLength={CODE_LENGTH}
                                value={code}
                                onChange={setCode}
                                disabled={processing || locked}
                                pattern={REGEXP_ONLY_DIGITS}
                                autoFocus
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: CODE_LENGTH },
                                        (_, i) => (
                                            <InputOTPSlot
                                                key={i}
                                                index={i}
                                                className="h-12 w-11 border-souk-brown/25 text-lg text-souk-title"
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                        </div>

                        <InputError
                            message={errors.code}
                            className="mt-2 text-center"
                        />

                        <button
                            type="submit"
                            disabled={
                                processing ||
                                locked ||
                                code.length < CODE_LENGTH
                            }
                            data-test={testId}
                            className="group mt-5 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Spinner />}
                            {submitLabel}
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

            <div className="mt-5 border-t border-souk-brown/10 pt-4 text-center">
                {secondsLeft > 0 ? (
                    <p className="text-xs text-souk-brown-deep/50">
                        {t('common.auth.login.code_resend_in').replace(
                            ':seconds',
                            String(secondsLeft),
                        )}
                    </p>
                ) : (
                    <button
                        type="button"
                        onClick={() =>
                            router.post(resendUrl, {}, { preserveScroll: true })
                        }
                        className="group inline-flex items-center gap-2 text-sm text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                    >
                        <RotateCcw className="h-3.5 w-3.5" strokeWidth={1.5} />
                        {t('common.auth.login.code_resend')}
                    </button>
                )}

                <p className="mt-2.5 text-[11px] leading-relaxed text-souk-brown-deep/45">
                    {t('common.auth.login.code_spam_hint')}
                </p>
            </div>
        </>
    );
}
