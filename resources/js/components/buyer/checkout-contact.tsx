import { router, useForm, usePoll } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Mail, PencilLine, RotateCcw, ShieldCheck } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useState } from 'react';
import ContactInput from '@/components/contact-input';
import CountryFlag from '@/components/country-flag';
import InputError from '@/components/input-error';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

const CODE_LENGTH = 6;

/** What CheckoutContactService::state() says the box should show. */
export type CheckoutContactState =
    | { step: 'enter' }
    | {
          /*
           * `confirmed`: the link in the signup email was opened (on a phone,
           * say) — the box finishes the signup here without a code.
           */
          step: 'code' | 'confirmed';
          /** As typed, written out in full: `+212 6 12 34 56 78`. */
          value: string;
          channel: 'email' | 'phone';
          region: string | null;
          resendIn: number;
          locked: boolean;
      };

/*
 * Every request here keeps the rest of the page as it is: the visitor may
 * already have typed their name and address below, and confirming a code
 * must not wipe that.
 */
const KEEP = { preserveScroll: true, preserveState: true } as const;

/**
 * The top of checkout for a visitor who has not signed in: where should the
 * order details go?
 *
 * An email or a phone number, then a 6-digit code sent there. The code is
 * what catches a wrong one — a mistyped address or number never receives it,
 * so it gets fixed here, before the order exists, rather than discovered when
 * the confirmation never arrives. Typing the code back signs the visitor in
 * (to the account that contact already has, or a new one), which is what
 * paying needs.
 *
 * No <form> of its own: it sits inside the checkout's form, and nested forms
 * are invalid HTML. Its buttons are `type="button"` and Enter is handled by
 * hand, so neither can submit the order.
 */
export default function CheckoutContact({
    state,
}: {
    state: CheckoutContactState;
}) {
    const { t } = useTranslation();

    return (
        <div
            id="checkout-contact"
            className="rounded-xl border border-souk-brown/20 bg-souk-peach/30 px-5 py-5 sm:px-6"
        >
            <h2 className="font-display text-xl text-souk-title">
                {t('buyer.checkout.contact_heading')}
            </h2>

            {state.step === 'enter' ? (
                <EnterContact />
            ) : state.step === 'confirmed' ? (
                <FinishConfirmed />
            ) : (
                <EnterCode state={state} />
            )}
        </div>
    );
}

function EnterContact() {
    const { t } = useTranslation();
    const form = useForm({ contact: '' });

    const send = () => {
        if (form.processing || form.data.contact.trim() === '') {
            return;
        }

        form.post(route('checkout.contact.send'), KEEP);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            send();
        }
    };

    return (
        <>
            <p className="mt-1.5 text-sm leading-relaxed text-souk-brown-deep/70">
                {t('buyer.checkout.contact_intro')}
            </p>

            <div
                className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start"
                onKeyDown={onKeyDown}
            >
                <div className="min-w-0 flex-1">
                    <ContactInput
                        id="contact"
                        name="contact"
                        value={form.data.contact}
                        onChange={(value) => {
                            form.setData('contact', value);
                            form.clearErrors('contact');
                        }}
                        error={form.errors.contact}
                        required={false}
                        boxClassName="rounded-lg border border-souk-brown/30 bg-white px-4 transition-colors focus-within:border-souk-brown"
                    />
                </div>

                <button
                    type="button"
                    onClick={send}
                    disabled={
                        form.processing || form.data.contact.trim() === ''
                    }
                    data-test="checkout-contact-send"
                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-souk-brown px-5 py-3 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing && <Spinner />}
                    {t('buyer.checkout.contact_send')}
                </button>
            </div>
        </>
    );
}

/*
 * What the box reloads while it waits: itself, plus what changes once the
 * visitor is signed in (another tab of this browser may have finished the
 * signup from the email's link).
 */
const WAITING_PROPS = ['contact', 'customer', 'addresses', 'auth'];

/**
 * The email's link was opened elsewhere and proved the address: finish the
 * signup here, where the basket is, with no code to type.
 */
function FinishConfirmed() {
    const { t } = useTranslation();

    useEffect(() => {
        router.post(route('checkout.contact.verify'), {}, KEEP);
    }, []);

    return (
        <p className="mt-3 flex items-center gap-2 text-sm text-souk-brown-deep/75">
            <Spinner />
            {t('buyer.checkout.contact_link_confirmed')}
        </p>
    );
}

function EnterCode({
    state,
}: {
    state: Extract<CheckoutContactState, { step: 'code' | 'confirmed' }>;
}) {
    const { t } = useTranslation();
    const form = useForm({ code: '' });

    /*
     * A signup email carries a "confirm" link as well as the code. Whoever
     * clicks it instead of typing the code is noticed here within a few
     * seconds — whether the link opened in this browser (now signed in) or on
     * another device (the signup is now confirmed) — and the page moves on.
     */
    usePoll(3000, { only: WAITING_PROPS }, { keepAlive: false });

    /*
     * Counts down from what the server said was left, and restarts when a
     * resend brings a fresh figure — see CodeEntry for why this is derived
     * during render rather than in an effect.
     */
    const [cooldown, setCooldown] = useState({
        from: state.resendIn,
        left: state.resendIn,
    });

    if (cooldown.from !== state.resendIn) {
        setCooldown({ from: state.resendIn, left: state.resendIn });
    }

    useEffect(() => {
        if (cooldown.left <= 0) {
            return;
        }

        const timer = setTimeout(
            () => setCooldown((c) => ({ ...c, left: c.left - 1 })),
            1000,
        );

        return () => clearTimeout(timer);
    }, [cooldown.left]);

    const verify = (code: string) => {
        if (form.processing || code.length < CODE_LENGTH) {
            return;
        }

        form.transform(() => ({ code }));
        form.post(route('checkout.contact.verify'), {
            ...KEEP,
            // A wrong code: empty the boxes for the next try.
            onError: () => form.setData('code', ''),
        });
    };

    const change = () => router.post(route('checkout.contact.reset'), {}, KEEP);

    const resend = () =>
        router.post(route('checkout.contact.resend'), {}, KEEP);

    return (
        <>
            {/* Exactly where the code went, in full, with a way to fix it. */}
            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-souk-brown-deep/75">
                <span>
                    {state.channel === 'phone'
                        ? t('buyer.checkout.contact_sent_sms')
                        : t('buyer.checkout.contact_sent_email')}
                </span>
                <span
                    className="inline-flex items-center gap-2 font-medium text-souk-title"
                    dir="ltr"
                >
                    {state.channel === 'phone' ? (
                        <CountryFlag country={state.region} />
                    ) : (
                        <Mail
                            className="h-4 w-4 text-souk-brown/60"
                            strokeWidth={1.6}
                        />
                    )}
                    <bdi>{state.value}</bdi>
                </span>
                <button
                    type="button"
                    onClick={change}
                    data-test="checkout-contact-change"
                    className="inline-flex items-center gap-1.5 text-souk-brown underline underline-offset-4 hover:text-souk-brown-deep"
                >
                    <PencilLine className="h-3.5 w-3.5" strokeWidth={1.6} />
                    {t('buyer.checkout.contact_change')}
                </button>
            </div>

            <label className="mt-4 mb-2 block text-xs text-souk-brown-deep/60">
                {t('buyer.checkout.contact_code_label')}
            </label>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                {/* Left to right even in Arabic: the digits are one token. */}
                <div dir="ltr">
                    <InputOTP
                        maxLength={CODE_LENGTH}
                        value={form.data.code}
                        onChange={(code) => {
                            form.setData('code', code);
                            form.clearErrors('code');
                        }}
                        // Six digits in: confirm without another tap.
                        onComplete={verify}
                        disabled={form.processing || state.locked}
                        pattern={REGEXP_ONLY_DIGITS}
                        autoFocus
                    >
                        <InputOTPGroup>
                            {Array.from({ length: CODE_LENGTH }, (_, i) => (
                                <InputOTPSlot
                                    key={i}
                                    index={i}
                                    className="h-11 w-10 border-souk-brown/25 bg-white text-lg text-souk-title"
                                />
                            ))}
                        </InputOTPGroup>
                    </InputOTP>
                </div>

                <button
                    type="button"
                    onClick={() => verify(form.data.code)}
                    disabled={
                        form.processing ||
                        state.locked ||
                        form.data.code.length < CODE_LENGTH
                    }
                    data-test="checkout-contact-verify"
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-souk-brown px-5 py-3 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <ShieldCheck className="h-4 w-4" strokeWidth={1.6} />
                    )}
                    {t('buyer.checkout.contact_verify')}
                </button>
            </div>

            {/* `contact` comes back when the code has expired meanwhile. */}
            <InputError
                message={
                    form.errors.code ??
                    (form.errors as Record<string, string | undefined>).contact
                }
                className="mt-2"
            />

            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                {cooldown.left > 0 ? (
                    <span className="text-souk-brown-deep/50">
                        {t('buyer.checkout.contact_resend_in').replace(
                            ':seconds',
                            String(cooldown.left),
                        )}
                    </span>
                ) : (
                    <button
                        type="button"
                        onClick={resend}
                        className="inline-flex items-center gap-1.5 text-souk-brown underline underline-offset-4 hover:text-souk-brown-deep"
                    >
                        <RotateCcw className="h-3 w-3" strokeWidth={1.6} />
                        {t('buyer.checkout.contact_resend')}
                    </button>
                )}
                <span className="text-souk-brown-deep/55">
                    {t('buyer.checkout.contact_wrong_hint')}
                </span>
            </div>

            {state.channel === 'email' && (
                <p className="mt-2 text-xs text-souk-brown-deep/60">
                    {t('buyer.checkout.contact_or_link')}
                </p>
            )}

            <p className="mt-2 text-[11px] leading-relaxed text-souk-brown-deep/45">
                {t('buyer.checkout.contact_why')}
            </p>
        </>
    );
}
