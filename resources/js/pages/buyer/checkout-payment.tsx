import { Head, Link, router, usePoll } from '@inertiajs/react';
import { AlertCircle, Clock, CreditCard, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';

/** How often the page asks whether Payzone's confirmation has landed. */
const POLL_MS = 3000;

/** After this long the page stops promising "a few seconds". */
const SLOW_AFTER_MS = 45000;

type PaymentState = {
    reference: string;
    /** `awaiting` a card payment, or `released` (expired / cancelled). */
    state: 'awaiting' | 'released';
    /** The latest attempt: pending, failed, expired… — null before the first. */
    last_attempt: string | null;
    /** MAD, delivery included — what the card is charged. */
    amount: number;
    /** What Payzone's redirect claimed. A hint for the wording, never proof. */
    outcome: 'success' | 'failure' | 'cancel' | null;
};

/**
 * Where a buyer paying by card lands back from Payzone.
 *
 * The browser coming back proves nothing — only Payzone's signed notification
 * places the order (PaymentService). So after a "success" redirect this page
 * waits, re-asking the server every few seconds; Buyer\PaymentController
 * redirects to the confirmation page as soon as the purchase is placed.
 * Otherwise it offers the ways forward: try the card again, or cancel. Card
 * is the only way to pay, so there is no other method to switch to.
 */
export default function CheckoutPayment({
    payment,
}: {
    payment: PaymentState;
}) {
    const { t } = useTranslation();
    const { baseMoney } = useCurrency();
    const [slow, setSlow] = useState(false);
    const [busy, setBusy] = useState(false);

    const confirming =
        payment.state === 'awaiting' &&
        payment.outcome === 'success' &&
        payment.last_attempt !== 'failed';

    const { stop } = usePoll(
        POLL_MS,
        { only: ['payment'] },
        { autoStart: confirming },
    );

    useEffect(() => {
        if (!confirming) {
            stop();

            return;
        }

        const timer = window.setTimeout(() => setSlow(true), SLOW_AFTER_MS);

        return () => window.clearTimeout(timer);
    }, [confirming, stop]);

    const post = (name: string) => {
        setBusy(true);
        router.post(
            route(name, payment.reference),
            {},
            { onFinish: () => setBusy(false) },
        );
    };

    const [title, body] = (() => {
        if (payment.state === 'released') {
            return [
                t('buyer.payment.released_title'),
                t('buyer.payment.released_body'),
            ];
        }

        if (confirming) {
            return [
                t('buyer.payment.awaiting_title'),
                slow
                    ? t('buyer.payment.slow_body')
                    : t('buyer.payment.awaiting_body'),
            ];
        }

        if (
            payment.last_attempt === 'failed' ||
            payment.outcome === 'failure'
        ) {
            return [
                t('buyer.payment.failed_title'),
                t('buyer.payment.failed_body'),
            ];
        }

        if (payment.outcome === 'cancel') {
            return [
                t('buyer.payment.cancel_title'),
                t('buyer.payment.cancel_body'),
            ];
        }

        return [
            t('buyer.payment.pending_title'),
            t('buyer.payment.pending_body'),
        ];
    })();

    const Icon = confirming
        ? Loader2
        : payment.state === 'released'
          ? Clock
          : AlertCircle;

    // While confirming, the choices only appear once it is taking long.
    const showActions = payment.state === 'awaiting' && (!confirming || slow);

    return (
        <div className="min-h-screen bg-souk-paper">
            <Head title={t('buyer.payment.title')} />
            <SiteHeader />

            <section className="container py-12 sm:py-16">
                <div className="mx-auto max-w-lg text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-souk-brown/10">
                        <Icon
                            className={`h-8 w-8 text-souk-brown ${confirming ? 'animate-spin' : ''}`}
                            strokeWidth={1.8}
                        />
                    </div>

                    <h1 className="mt-6 font-display text-3xl text-souk-title">
                        {title}
                    </h1>
                    <p
                        className="mx-auto mt-4 max-w-md text-base leading-relaxed text-souk-brown-deep/70"
                        aria-live="polite"
                    >
                        {body}
                    </p>

                    <dl className="mx-auto mt-8 flex max-w-sm justify-between gap-4 rounded-xl border border-souk-brown/15 bg-white px-5 py-3 text-start text-sm">
                        <div>
                            <dt className="text-[11px] tracking-[0.12em] text-souk-brown-deep/50 uppercase">
                                {t('buyer.payment.reference')}
                            </dt>
                            <dd
                                dir="ltr"
                                className="mt-0.5 font-mono font-semibold text-souk-title"
                            >
                                {payment.reference}
                            </dd>
                        </div>
                        <div className="text-end">
                            <dt className="text-[11px] tracking-[0.12em] text-souk-brown-deep/50 uppercase">
                                {t('buyer.payment.amount')}
                            </dt>
                            <dd className="mt-0.5 font-display font-semibold text-souk-title tabular-nums">
                                {baseMoney(payment.amount)}
                            </dd>
                        </div>
                    </dl>

                    {showActions && (
                        <div className="mx-auto mt-8 flex max-w-sm flex-col gap-3">
                            {/* A plain link, not an Inertia visit: the launch
                                page is HTML that posts itself to Payzone. */}
                            <a
                                href={route('checkout.pay', payment.reference)}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-souk-title px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep"
                            >
                                <CreditCard
                                    className="h-4 w-4"
                                    strokeWidth={1.8}
                                />
                                {t('buyer.payment.retry')}
                            </a>
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() => post('checkout.payment.cancel')}
                                className="text-sm text-souk-brown-deep/60 underline-offset-4 transition-colors hover:text-terracotta hover:underline disabled:opacity-50"
                            >
                                {t('buyer.payment.cancel_order')}
                            </button>
                        </div>
                    )}

                    {payment.state === 'released' && (
                        <Link
                            href={route('cart.show')}
                            className="mt-8 inline-flex items-center justify-center rounded-xl bg-souk-title px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.payment.back_to_cart')}
                        </Link>
                    )}
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
