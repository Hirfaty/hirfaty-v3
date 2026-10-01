import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ClipboardCheck,
    Home,
    MapPin,
    Package,
    ShoppingBag,
    Truck,
    XCircle,
} from 'lucide-react';
import OrderStatusText, {
    STATUS_COLOURS,
} from '@/components/buyer/OrderStatusText';
import { useTranslation } from '@/hooks/use-translation';
import BuyerAccountLayout from '@/layouts/buyer/account-layout';
import type {
    BuyerPurchase,
    OrderStatus,
    OrderTrackingEvent,
} from '@/types/orders';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/** The happy path, in order. `cancelled` is a way out of it, not a step. */
const STEPS = [
    { status: 'pending', icon: ShoppingBag, label: 'step_placed' },
    { status: 'confirmed', icon: ClipboardCheck, label: 'step_confirmed' },
    { status: 'shipped', icon: Truck, label: 'step_shipped' },
    { status: 'delivered', icon: Home, label: 'step_delivered' },
] as const;

const STEP_INDEX: Record<string, number> = {
    pending: 0,
    confirmed: 1,
    shipped: 2,
    delivered: 3,
};

/**
 * "Track my order": where a purchase has got to, and everything that
 * happened on the way — the steps it reached and the desk's notes on where
 * the parcel is.
 *
 * Built from OrderStatusEvent rows folded across the purchase's workshops
 * (BuyerAccountService::tracking). Orders placed before history was recorded
 * only know when they were placed and their current status, so a step can be
 * complete without a date — it is drawn done, just undated.
 */
export default function OrderTracking({
    purchase,
    events,
    reached,
}: {
    purchase: BuyerPurchase;
    events: OrderTrackingEvent[];
    reached: Partial<Record<OrderStatus, string>>;
}) {
    const { t, locale } = useTranslation();
    const tag = LOCALE_TAGS[locale as string] ?? 'fr-MA';

    const cancelled = purchase.status === 'cancelled';

    /*
     * How far along the bar is. For a cancelled purchase, as far as it got
     * before it was stopped — so the buyer can see whether it had shipped.
     */
    const current = cancelled
        ? Math.max(
              0,
              ...Object.keys(reached)
                  .filter((status) => status in STEP_INDEX)
                  .map((status) => STEP_INDEX[status]),
          )
        : (STEP_INDEX[purchase.status] ?? 0);

    const day = (value: string) =>
        new Intl.DateTimeFormat(tag, {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value));

    const moment = (value: string) =>
        new Intl.DateTimeFormat(tag, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));

    return (
        <BuyerAccountLayout
            active="orders"
            title={t('buyer.tracking.title')}
            description={t('buyer.tracking.intro')}
        >
            <Link
                href={route('buyer.account.orders.index')}
                className="mb-5 inline-flex items-center gap-2 text-sm text-souk-brown-deep/60 transition-colors hover:text-souk-title"
            >
                <ArrowLeft
                    className="h-4 w-4 rtl:rotate-180"
                    strokeWidth={1.6}
                />
                {t('buyer.orders.back')}
            </Link>

            <div className="space-y-5">
                {/* ── Where it is now ── */}
                <section className="rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-display text-lg text-souk-title">
                                {t('buyer.orders.number').replace(
                                    ':reference',
                                    '',
                                )}
                                <bdi dir="ltr">{purchase.reference}</bdi>
                            </h2>
                            {(purchase.delivery_service_name ||
                                purchase.tracking_numbers.length > 0) && (
                                <p className="mt-1 text-sm text-souk-brown-deep/60">
                                    {purchase.delivery_service_name && (
                                        <>
                                            {t('buyer.tracking.carrier')}:{' '}
                                            {purchase.delivery_service_name}
                                        </>
                                    )}
                                    {purchase.tracking_numbers.length > 0 && (
                                        <>
                                            {purchase.delivery_service_name &&
                                                ' · '}
                                            {t(
                                                'buyer.tracking.tracking_number',
                                            )}
                                            :{' '}
                                            <bdi
                                                dir="ltr"
                                                className="font-mono text-souk-title"
                                            >
                                                {purchase.tracking_numbers.join(
                                                    ', ',
                                                )}
                                            </bdi>
                                        </>
                                    )}
                                </p>
                            )}
                        </div>
                        <OrderStatusText
                            status={purchase.status}
                            className="text-sm"
                        />
                    </div>

                    {cancelled && (
                        <p className="mt-4 flex items-center gap-2 rounded-lg bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700">
                            <XCircle className="h-4 w-4 shrink-0" />
                            {t('buyer.tracking.cancelled_notice')}
                        </p>
                    )}

                    {/* The stepper. Four columns at every width — the labels
                        are short enough for a phone, and a vertical bar here
                        would duplicate the history below. */}
                    <ol className="mt-6 grid grid-cols-4">
                        {STEPS.map((step, index) => {
                            const done = index <= current;
                            const Icon = step.icon;
                            const colours = cancelled
                                ? 'bg-souk-brown-deep/30 text-white'
                                : done
                                  ? `${STATUS_COLOURS[purchase.status].dot} text-white`
                                  : 'bg-souk-peach text-souk-brown-deep/40';
                            const at = reached[step.status];

                            return (
                                <li
                                    key={step.status}
                                    className="relative flex flex-col items-center text-center"
                                >
                                    {/* The rail to the next step, drawn from
                                        this one's centre so it meets the next
                                        circle whatever the column width. */}
                                    {index < STEPS.length - 1 && (
                                        <span
                                            aria-hidden
                                            className={`absolute top-5 h-0.5 w-full ltr:left-1/2 rtl:right-1/2 ${
                                                index < current && !cancelled
                                                    ? STATUS_COLOURS[
                                                          purchase.status
                                                      ].dot
                                                    : 'bg-souk-brown/15'
                                            }`}
                                        />
                                    )}
                                    <span
                                        className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full ring-4 ring-white ${colours}`}
                                    >
                                        {done && index < current ? (
                                            <Check
                                                className="h-5 w-5"
                                                strokeWidth={2.4}
                                            />
                                        ) : (
                                            <Icon
                                                className="h-5 w-5"
                                                strokeWidth={1.8}
                                            />
                                        )}
                                    </span>
                                    <span
                                        className={`mt-2 text-xs sm:text-sm ${
                                            done
                                                ? 'font-semibold text-souk-title'
                                                : 'text-souk-brown-deep/50'
                                        }`}
                                    >
                                        {t(`buyer.tracking.${step.label}`)}
                                    </span>
                                    {at && done && (
                                        <span className="mt-0.5 text-[11px] text-souk-brown-deep/50 sm:text-xs">
                                            {day(at)}
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </section>

                {/* ── Everything that happened ── */}
                <section className="rounded-2xl bg-white p-5 shadow-sm shadow-souk-brown/5 sm:p-6">
                    <h2 className="font-display text-lg text-souk-title">
                        {t('buyer.tracking.history')}
                    </h2>

                    <ol className="mt-5">
                        {events.map((event, index) => {
                            const last = index === events.length - 1;
                            const Icon = event.status ? Package : MapPin;
                            const dot = event.status
                                ? STATUS_COLOURS[event.status].dot
                                : 'bg-souk-brown';

                            return (
                                <li
                                    key={event.id}
                                    className="relative flex gap-4"
                                >
                                    {/* The thread joining this event to the
                                        one before it. */}
                                    {!last && (
                                        <span
                                            aria-hidden
                                            className="absolute top-8 bottom-0 w-px bg-souk-brown/15 ltr:left-4 rtl:right-4"
                                        />
                                    )}
                                    <span
                                        className={`relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white ${dot} ${
                                            index === 0 ? '' : 'opacity-80'
                                        }`}
                                    >
                                        <Icon
                                            className="h-4 w-4"
                                            strokeWidth={1.8}
                                        />
                                    </span>
                                    <div
                                        className={`min-w-0 flex-1 ${last ? '' : 'pb-6'}`}
                                    >
                                        <p
                                            className={`text-sm ${
                                                index === 0
                                                    ? 'font-semibold text-souk-title'
                                                    : 'font-medium text-souk-brown-deep/85'
                                            }`}
                                        >
                                            {t(
                                                `buyer.tracking.event_${event.status ?? 'update'}`,
                                            )}
                                        </p>
                                        {event.note && (
                                            <p className="mt-0.5 text-sm text-souk-brown-deep/70">
                                                {event.note}
                                            </p>
                                        )}
                                        {event.stores && (
                                            <p className="mt-0.5 text-xs text-souk-brown-deep/55">
                                                {t(
                                                    'buyer.tracking.only',
                                                ).replace(
                                                    ':names',
                                                    event.stores.join(', '),
                                                )}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-souk-brown-deep/50 tabular-nums">
                                            {moment(event.at)}
                                        </p>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </section>
            </div>
        </BuyerAccountLayout>
    );
}
