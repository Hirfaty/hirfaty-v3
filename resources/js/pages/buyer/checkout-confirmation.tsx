import { Head, Link } from '@inertiajs/react';
import { Check, ClipboardCheck, Package, Star } from 'lucide-react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import type { BuyerPurchase } from '@/types/orders';

/**
 * Where checkout lands: the congratulations, and what happens next.
 *
 * A page of its own, keyed by the purchase reference, rather than a dialog
 * fired by a one-shot flash — so a reload still shows it, and the summary
 * below is the same shape as the purchase's card on the orders page
 * (BuyerAccountService::purchase()), however many workshops it was split
 * across.
 */
export default function CheckoutConfirmation({
    purchase,
    email,
}: {
    purchase: BuyerPurchase;
    email: string | null;
}) {
    const { t } = useTranslation();
    const { money } = useCurrency();

    const items = purchase.orders.flatMap((order) => order.items);
    const address = [purchase.shipping_address, purchase.city]
        .filter(Boolean)
        .join(', ');

    const [emailBefore, emailAfter = ''] = t('buyer.order_placed.email').split(
        ':email',
    );

    const steps = [
        { icon: ClipboardCheck, text: t('buyer.order_placed.step_confirm') },
        { icon: Package, text: t('buyer.order_placed.step_ship') },
        { icon: Star, text: t('buyer.order_placed.step_deliver') },
    ];

    return (
        <div className="min-h-screen bg-souk-paper">
            <Head title={t('buyer.order_placed.title')} />
            <SiteHeader />

            <section className="container py-12 sm:py-16">
                <div className="mx-auto max-w-2xl">
                    {/* ── The congratulations ── */}
                    <div className="text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-olive/15">
                            <Check
                                className="h-8 w-8 text-olive"
                                strokeWidth={2.2}
                            />
                        </div>
                        <p className="mt-6 text-xs font-semibold tracking-[0.18em] text-souk-brown uppercase">
                            {t('buyer.order_placed.eyebrow')}
                        </p>
                        <h1 className="mt-2 font-display text-3xl text-souk-title sm:text-4xl">
                            {t('buyer.order_placed.title')}
                        </h1>
                        <p className="mx-auto mt-4 max-w-lg text-base leading-relaxed text-souk-brown-deep/70">
                            {t('buyer.order_placed.body')}
                        </p>

                        <div className="mx-auto mt-6 inline-block rounded-xl border border-souk-brown/15 bg-white px-5 py-3">
                            <p className="text-[11px] tracking-[0.12em] text-souk-brown-deep/50 uppercase">
                                {t('buyer.order_placed.reference')}
                            </p>
                            <p
                                dir="ltr"
                                className="mt-0.5 font-mono text-base font-semibold tracking-wide text-souk-title"
                            >
                                {purchase.reference}
                            </p>
                        </div>

                        {email && (
                            <p className="mt-3 text-sm text-souk-brown-deep/55">
                                {/* Isolated as LTR so, in Arabic, the
                                    sentence's full stop stays at its end. */}
                                {emailBefore}
                                <bdi dir="ltr">{email}</bdi>
                                {emailAfter}
                            </p>
                        )}
                    </div>

                    {/* ── What was bought ── */}
                    <div className="mt-10 rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6">
                        <h2 className="font-display text-lg text-souk-title">
                            {t('buyer.order_placed.summary')}
                        </h2>

                        <ul className="mt-4 divide-y divide-souk-brown/10">
                            {items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-center gap-4 py-3"
                                >
                                    <span className="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-souk-peach">
                                        {item.product.image && (
                                            <img
                                                src={item.product.image}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </span>
                                    <div className="min-w-0 flex-1 text-sm">
                                        <p className="truncate font-medium text-souk-title">
                                            {item.product.name}
                                        </p>
                                        {item.variant_label && (
                                            <p className="text-souk-brown-deep/55">
                                                {item.variant_label}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 text-sm text-souk-brown-deep/55">
                                        ×{item.quantity}
                                    </span>
                                    <span className="shrink-0 text-sm text-souk-brown-deep/80 tabular-nums">
                                        {money(item.subtotal)}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <dl className="mt-2 space-y-1.5 border-t border-souk-brown/12 pt-4 text-sm">
                            <div className="flex justify-between gap-4 text-souk-brown-deep/60">
                                <dt>{t('buyer.orders.items_total')}</dt>
                                <dd className="tabular-nums">
                                    {money(purchase.items_total)}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 text-souk-brown-deep/60">
                                <dt>{t('buyer.orders.delivery')}</dt>
                                <dd className="tabular-nums">
                                    {money(purchase.delivery_fee)}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 pt-1 text-souk-title">
                                <dt className="font-semibold">
                                    {t('buyer.orders.total')}
                                </dt>
                                <dd className="font-display font-semibold tabular-nums">
                                    {money(purchase.total_amount)} TTC
                                </dd>
                            </div>
                            {/* Absent for a purchase from before online payment. */}
                            {purchase.payment_method !== 'cod' && (
                                <div className="flex justify-between gap-4 pt-2 text-souk-brown-deep/60">
                                    <dt>{t('buyer.orders.payment')}</dt>
                                    <dd className="text-end">
                                        {purchase.payment_method === 'paypal'
                                            ? t('buyer.orders.payment_paypal')
                                            : t('buyer.orders.payment_card')}
                                    </dd>
                                </div>
                            )}
                            {address && (
                                <div className="flex justify-between gap-4 pt-2 text-souk-brown-deep/60">
                                    <dt>{t('buyer.orders.delivered_to')}</dt>
                                    <dd className="text-end">{address}</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {/* ── What happens next ── */}
                    <div className="mt-6 rounded-2xl bg-souk-peach/60 p-5 sm:p-6">
                        <h2 className="font-display text-lg text-souk-title">
                            {t('buyer.order_placed.next_title')}
                        </h2>
                        <ol className="mt-4 space-y-4">
                            {steps.map(({ icon: Icon, text }, index) => (
                                <li key={index} className="flex gap-3">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-souk-brown">
                                        <Icon
                                            className="h-4 w-4"
                                            strokeWidth={1.8}
                                        />
                                    </span>
                                    <p className="pt-1 text-sm leading-relaxed text-souk-brown-deep/75">
                                        {text}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <Link
                            href={route('buyer.account.orders.index')}
                            className="inline-flex items-center justify-center rounded-xl bg-souk-brown px-6 py-3 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.order_placed.view')}
                        </Link>
                        <Link
                            href={route('products.index')}
                            className="inline-flex items-center justify-center rounded-xl border border-souk-brown/20 px-6 py-3 text-sm font-medium text-souk-title transition-colors hover:bg-white"
                        >
                            {t('buyer.order_placed.continue')}
                        </Link>
                    </div>
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
