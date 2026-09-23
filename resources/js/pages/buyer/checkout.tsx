import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Banknote, Check, Truck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Charged when the chosen destination somehow has no rate on file — mirrors
 * `DeliveryFeeService::FALLBACK_FEE` so the quote and the bill agree even in
 * that corner. One admin-run delivery covers the whole basket, so whatever
 * figure applies is charged once, not per workshop or per product.
 */
const FALLBACK_DELIVERY_FEE = 40;

/** A carrier the buyer can check out with, as CheckoutController hands it over. */
type DeliveryServiceOption = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
};

/** One service's rate card, as the checkout page consumes it. */
type RateCard = {
    countries: { code: string; name: string; fee: number | null }[];
    /** Per Moroccan city, keyed by city id. */
    cityFees: Record<string, number>;
};

/**
 * Every active service's card, keyed by service slug. The page picks the
 * selected service's card for the quote, so adding a carrier later is purely
 * a data change — the third carrier shows up here with no frontend edit.
 */
type DeliveryRatesMap = Record<string, RateCard>;

/** The empty card a service with no rates on file falls back to. */
const EMPTY_CARD: RateCard = { countries: [], cityFees: {} };

/**
 * Shared by every control on this page.
 *
 * Deliberately flat and light: the reference design leans on a pale field on
 * white rather than on borders, so the eye follows the labels down the column
 * instead of tracing boxes.
 */
const FIELD =
    'w-full rounded-lg border border-souk-brown/15 bg-souk-paper/60 px-3.5 py-2.5 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/35 focus:border-souk-brown focus:bg-white focus:outline-none';

/** Same box, but for something the buyer cannot change here. */
const FIELD_LOCKED =
    'w-full cursor-not-allowed rounded-lg border border-souk-brown/10 bg-souk-brown/[0.04] px-3.5 py-2.5 text-sm text-souk-brown-deep/60';

/**
 * Opts a `<label>` out of the global eyebrow treatment.
 *
 * app.css styles every label in the app as 11px, wide-tracked and UPPERCASE.
 * That is right for a one-word field caption and wrong for the labels on this
 * page, which either read as sentences ("First name") or wrap whole blocks of
 * body copy — a saved address, a payment description. Both `text-transform`
 * and `letter-spacing` inherit, so an inner span setting its own size and
 * colour does not undo them; they have to be cancelled on the label itself.
 */
const LABEL_RESET = 'mb-0 tracking-normal normal-case';

type CheckoutLine = {
    id: number;
    name: string;
    store_id: number | null;
    store_name: string;
    image: string | null;
    variant_label: string | null;
    quantity: number;
    unit_price: number | null;
    line_total: number | null;
};

type SavedAddress = {
    id: number;
    label: string | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    address_line: string;
    city_id: number;
    city_name: string | null;
    postal_code: string | null;
    country: string;
    is_default: boolean;
};

type City = { id: number; name: string };

type Customer = {
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
};

/** Sentinel for the "ship somewhere else" radio, so one control drives both. */
const NEW_ADDRESS = 'new';

/**
 * Declared rather than inferred: `address_id`, `city_id` and the name fields
 * are genuinely nullable (an empty address book, an account with no name), and
 * letting useForm infer them from the initial values narrows them to
 * non-nullable, which then rejects clearing them.
 */
type CheckoutForm = {
    first_name: string;
    last_name: string;
    shipping_phone: string;
    address_id: number | null;
    shipping_address: string;
    city_id: number | null;
    postal_code: string;
    country: string;
    payment_method: string;
    /** Which carrier ships the basket — one of `deliveryServices`. */
    delivery_service_id: number | null;
    save_address: boolean;
    address_label: string;
    notes: string;
};

function SectionHeading({ children }: { children: ReactNode }) {
    return <h2 className="font-display text-lg text-souk-title">{children}</h2>;
}

/**
 * Label above a field. The red dot marks required, matching the reference —
 * `aria-hidden` because "required" is already carried by the input's own
 * attribute, and a screen reader announcing a bullet adds nothing.
 */
function FieldLabel({
    children,
    htmlFor,
    required,
}: {
    children: ReactNode;
    htmlFor?: string;
    required?: boolean;
}) {
    return (
        <label
            htmlFor={htmlFor}
            className={`${LABEL_RESET} mb-1.5 block text-xs text-souk-brown-deep/70`}
        >
            {children}
            {required && (
                <span aria-hidden className="ms-1 text-terracotta">
                    •
                </span>
            )}
        </label>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-terracotta">{message}</p>;
}

const Checkout = ({
    items,
    cities,
    addresses,
    customer,
    paymentMethods,
    defaultCountry,
    deliveryServices,
    deliveryRates,
    deliveryShowDestinations,
    deliveryFlatFee,
}: {
    items: CheckoutLine[];
    cities: City[];
    addresses: SavedAddress[];
    customer: Customer;
    paymentMethods: string[];
    defaultCountry: string;
    deliveryServices: DeliveryServiceOption[];
    deliveryRates: DeliveryRatesMap;
    /**
     * The admin toggle. Off: no country or city picker, Morocco only, and
     * every basket pays `deliveryFlatFee` — see DeliverySettingsService.
     */
    deliveryShowDestinations: boolean;
    deliveryFlatFee: number;
}) => {
    const { t } = useTranslation();

    // Default first (the query orders on is_default), so a buyer with a book
    // lands on their usual address rather than an empty form.
    const preselected = addresses.find((a) => a.is_default) ?? addresses[0];

    const [choice, setChoice] = useState<number | typeof NEW_ADDRESS>(
        preselected ? preselected.id : NEW_ADDRESS,
    );

    const { data, setData, post, processing, errors } = useForm<CheckoutForm>({
        /*
         * Pre-filled from the account where it knows, blank where it does not.
         * Signup asks only for a password now, so an empty name here is the
         * normal case rather than an edge one — and whatever is typed is
         * written back to the account if it had none, so this is the last time
         * they fill it in. See CheckoutDestinationService::backfillProfile.
         */
        first_name: customer.first_name ?? '',
        last_name: customer.last_name ?? '',
        shipping_phone: preselected?.recipient_phone ?? customer.phone ?? '',
        address_id: preselected?.id ?? null,
        shipping_address: '',
        // No city picker while destinations are hidden, so nothing to send.
        city_id: deliveryShowDestinations ? (cities[0]?.id ?? null) : null,
        postal_code: '',
        country: defaultCountry,
        // Cash on delivery is the only method the server accepts today, so it
        // is selected rather than offered as a choice of one.
        payment_method: paymentMethods[0] ?? 'cod',
        // The first carrier in display order (Amana today) is pre-selected.
        delivery_service_id: deliveryServices[0]?.id ?? null,
        save_address: false,
        address_label: '',
        notes: '',
    });

    /*
     * The discount code is its own form, posted to its own endpoint and
     * reading its own error bag, so a rejected code cannot mark the delivery
     * fields invalid — and typing one does not disturb the main form's state.
     * There is no coupon table behind it yet; see
     * CheckoutController::applyCoupon.
     */
    const coupon = useForm({ code: '' });

    const applyCoupon = () =>
        coupon.post(route('checkout.coupon'), {
            preserveScroll: true,
            errorBag: 'coupon',
        });

    /*
     * `cart` is a whole-basket rejection (empty cart, a line that went out of
     * stock or lost its price mid-checkout), not a field error — the server
     * raises it via withErrors, so it never appears in the form's own typed
     * error keys.
     */
    const cartError = (errors as Record<string, string | undefined>).cart;

    /*
     * Totals arrive in MAD. `money()` shows them in whatever the buyer is
     * reading; `baseMoney()` keeps a figure in dirhams, which the total below
     * needs — the courier collects MAD whatever the page is displaying.
     */
    const { money, baseMoney, currency, isBase } = useCurrency();

    const chooseSaved = (address: SavedAddress) => {
        setChoice(address.id);
        setData((current) => ({
            ...current,
            address_id: address.id,
            // Cleared so a half-typed new address can't ride along and be
            // saved to the book behind the buyer's back.
            shipping_address: '',
            postal_code: '',
            save_address: false,
            address_label: '',
            shipping_phone:
                address.recipient_phone ?? current.shipping_phone ?? '',
        }));
    };

    const chooseNew = () => {
        setChoice(NEW_ADDRESS);
        setData((current) => ({
            ...current,
            address_id: null,
            shipping_phone: current.shipping_phone || (customer.phone ?? ''),
        }));
    };

    /** Morocco is priced per city; everywhere else has one rate per country. */
    const isDomestic = data.country === defaultCountry;

    /*
     * Switching to a country with no city list would otherwise leave the
     * previously selected Moroccan city attached, which the server rejects
     * (see CheckoutRequest) and which would quote the wrong fee.
     */
    const chooseCountry = (code: string) => {
        setData((current) => ({
            ...current,
            country: code,
            city_id: code === defaultCountry ? (cities[0]?.id ?? null) : null,
        }));
    };

    /*
     * The carrier the buyer picked decides which rate card the quote reads
     * off. CheckoutService re-resolves the same service server-side when the
     * order is placed, so a stale pick here can only under-quote, never
     * under-bill — and the server ignores unknown ids.
     */
    const selectedService =
        deliveryServices.find((s) => s.id === data.delivery_service_id) ??
        deliveryServices[0];

    const rates = selectedService
        ? (deliveryRates[selectedService.slug] ?? EMPTY_CARD)
        : EMPTY_CARD;

    /*
     * Checkout still splits the basket into one order per workshop for
     * fulfilment, but delivery is one job to one address, so the fee is
     * charged once and follows the destination rather than the basket.
     * CheckoutService resolves this same figure server-side when the order is
     * placed — this is only the quote.
     */
    const subtotal = items.reduce(
        (sum, line) => sum + (line.line_total ?? 0),
        0,
    );

    const deliveryTotal = (() => {
        // Destinations hidden: one fee for every basket, nothing to look up.
        if (!deliveryShowDestinations) {
            return deliveryFlatFee;
        }

        if (isDomestic) {
            const cityFee =
                data.city_id === null
                    ? undefined
                    : rates.cityFees[String(data.city_id)];

            return cityFee ?? FALLBACK_DELIVERY_FEE;
        }

        const country = rates.countries.find((c) => c.code === data.country);

        return country?.fee ?? FALLBACK_DELIVERY_FEE;
    })();

    const grandTotal = subtotal + deliveryTotal;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    return (
        <div className="min-h-screen bg-white">
            <Head title={t('buyer.checkout.title')} />
            <SiteHeader />

            <section className="container pt-8 pb-4">
                <Link
                    href={route('cart.show')}
                    className="inline-flex items-center gap-2 text-xs text-souk-brown-deep/60 transition-colors hover:text-souk-title"
                >
                    <ArrowLeft className="h-3.5 w-3.5" strokeWidth={1.6} />
                    {t('buyer.checkout.back')}
                </Link>
            </section>

            <section className="container pb-20">
                {cartError && (
                    <div className="mb-8 flex items-start gap-3 rounded-lg border border-terracotta/25 bg-terracotta/5 px-4 py-3.5 text-sm text-terracotta">
                        <AlertCircle
                            className="mt-0.5 h-4 w-4 shrink-0"
                            strokeWidth={1.6}
                        />
                        <p>{cartError}</p>
                    </div>
                )}

                {/*
                 * Two columns from `lg`: the form on the leading edge, the
                 * summary on the trailing one — and both mirror under RTL,
                 * since the grid uses no physical direction of its own.
                 */}
                <form
                    onSubmit={submit}
                    className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:gap-16"
                >
                    {/* ══ Left: the form ═══════════════════════════ */}
                    <div>
                        <h1 className="font-display text-3xl text-souk-title">
                            {t('buyer.checkout.title')}
                        </h1>

                        {/* ── Customer details ──────────────────── */}
                        <div className="mt-8">
                            <SectionHeading>
                                {t('buyer.checkout.customer_heading')}
                            </SectionHeading>

                            <div className="mt-4 grid gap-x-4 gap-y-4 sm:grid-cols-2">
                                <div>
                                    <FieldLabel htmlFor="first_name" required>
                                        {t('buyer.checkout.first_name')}
                                    </FieldLabel>
                                    <input
                                        id="first_name"
                                        type="text"
                                        required
                                        autoComplete="given-name"
                                        value={data.first_name}
                                        onChange={(e) =>
                                            setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'buyer.checkout.first_name_placeholder',
                                        )}
                                        className={FIELD}
                                    />
                                    <FieldError message={errors.first_name} />
                                </div>

                                <div>
                                    <FieldLabel htmlFor="last_name" required>
                                        {t('buyer.checkout.last_name')}
                                    </FieldLabel>
                                    <input
                                        id="last_name"
                                        type="text"
                                        required
                                        autoComplete="family-name"
                                        value={data.last_name}
                                        onChange={(e) =>
                                            setData('last_name', e.target.value)
                                        }
                                        placeholder={t(
                                            'buyer.checkout.last_name_placeholder',
                                        )}
                                        className={FIELD}
                                    />
                                    <FieldError message={errors.last_name} />
                                </div>

                                {/*
                                 * Shown, not asked for. This is the address the
                                 * buyer signs in with — /account locks it for
                                 * the same reason, and editing it here would
                                 * either do nothing or change their login.
                                 */}
                                <div>
                                    <FieldLabel htmlFor="email">
                                        {t('buyer.checkout.email')}
                                    </FieldLabel>
                                    <input
                                        id="email"
                                        type="email"
                                        readOnly
                                        disabled
                                        dir="ltr"
                                        value={customer.email ?? '—'}
                                        className={FIELD_LOCKED}
                                    />
                                </div>

                                <div>
                                    <FieldLabel
                                        htmlFor="shipping_phone"
                                        required
                                    >
                                        {t('buyer.checkout.phone')}
                                    </FieldLabel>
                                    <input
                                        id="shipping_phone"
                                        type="tel"
                                        inputMode="tel"
                                        required
                                        autoComplete="tel"
                                        dir="ltr"
                                        value={data.shipping_phone}
                                        onChange={(e) =>
                                            setData(
                                                'shipping_phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'buyer.checkout.phone_placeholder',
                                        )}
                                        className={FIELD}
                                    />
                                    <FieldError
                                        message={errors.shipping_phone}
                                    />
                                </div>
                            </div>

                            <p className="mt-2.5 flex items-center gap-1.5 text-xs text-souk-brown-deep/55">
                                <Truck
                                    className="h-3 w-3 shrink-0"
                                    strokeWidth={1.6}
                                />
                                {t('buyer.checkout.phone_hint')}
                            </p>
                        </div>

                        {/* ── Shipping details ──────────────────── */}
                        <div className="mt-10">
                            <SectionHeading>
                                {t('buyer.checkout.shipping_heading')}
                            </SectionHeading>

                            {/* Saved addresses, when the buyer has any. A real
                                radio group, so arrow keys move between them and
                                a screen reader announces "2 of 3". */}
                            {addresses.length > 0 && (
                                <fieldset className="mt-4">
                                    <legend className="mb-2 text-xs text-souk-brown-deep/70">
                                        {t('buyer.checkout.saved_addresses')}
                                    </legend>

                                    <div className="space-y-2">
                                        {addresses.map((address) => {
                                            const active =
                                                choice === address.id;

                                            return (
                                                <label
                                                    key={address.id}
                                                    className={`${LABEL_RESET} flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition-colors ${
                                                        active
                                                            ? 'border-souk-brown bg-souk-brown/[0.04]'
                                                            : 'border-souk-brown/15 hover:border-souk-brown/40'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="address_choice"
                                                        checked={active}
                                                        onChange={() =>
                                                            chooseSaved(address)
                                                        }
                                                        className="sr-only"
                                                    />
                                                    <span
                                                        aria-hidden
                                                        className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors ${
                                                            active
                                                                ? 'border-souk-brown bg-souk-brown text-souk-cream'
                                                                : 'border-souk-brown/30'
                                                        }`}
                                                    >
                                                        {active && (
                                                            <Check
                                                                className="h-2.5 w-2.5"
                                                                strokeWidth={3}
                                                            />
                                                        )}
                                                    </span>

                                                    <span className="min-w-0 flex-1 text-sm">
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium text-souk-title">
                                                                {address.label ||
                                                                    address.city_name}
                                                            </span>
                                                            {address.is_default && (
                                                                <span className="rounded-full bg-olive/12 px-2 py-0.5 text-[10px] font-medium text-olive">
                                                                    {t(
                                                                        'buyer.checkout.default_badge',
                                                                    )}
                                                                </span>
                                                            )}
                                                        </span>

                                                        <span className="mt-1 block text-[13px] leading-relaxed text-souk-brown-deep/70">
                                                            {
                                                                address.address_line
                                                            }
                                                            {address.city_name &&
                                                                `, ${address.city_name}`}
                                                            {address.postal_code &&
                                                                ` ${address.postal_code}`}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}

                                        <label
                                            className={`${LABEL_RESET} flex cursor-pointer items-center gap-3 rounded-lg border border-dashed p-3.5 text-sm transition-colors ${
                                                choice === NEW_ADDRESS
                                                    ? 'border-souk-brown bg-souk-brown/[0.04] text-souk-title'
                                                    : 'border-souk-brown/25 text-souk-brown-deep/70 hover:border-souk-brown/50'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="address_choice"
                                                checked={choice === NEW_ADDRESS}
                                                onChange={chooseNew}
                                                className="sr-only"
                                            />
                                            <span
                                                aria-hidden
                                                className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors ${
                                                    choice === NEW_ADDRESS
                                                        ? 'border-souk-brown bg-souk-brown text-souk-cream'
                                                        : 'border-souk-brown/30'
                                                }`}
                                            >
                                                {choice === NEW_ADDRESS && (
                                                    <Check
                                                        className="h-2.5 w-2.5"
                                                        strokeWidth={3}
                                                    />
                                                )}
                                            </span>
                                            <span className="font-medium">
                                                {t(
                                                    'buyer.checkout.use_new_address',
                                                )}
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>
                            )}

                            {/* The address itself. Always shown to a buyer with
                                an empty book, since there is nothing to pick. */}
                            {choice === NEW_ADDRESS && (
                                <div className="mt-4 space-y-4">
                                    <div>
                                        <FieldLabel
                                            htmlFor="shipping_address"
                                            required
                                        >
                                            {t('buyer.checkout.address_line')}
                                        </FieldLabel>
                                        <input
                                            id="shipping_address"
                                            type="text"
                                            autoComplete="street-address"
                                            value={data.shipping_address}
                                            onChange={(e) =>
                                                setData(
                                                    'shipping_address',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={t(
                                                deliveryShowDestinations
                                                    ? 'buyer.checkout.address_placeholder'
                                                    : 'buyer.checkout.address_placeholder_with_city',
                                            )}
                                            className={FIELD}
                                        />
                                        <FieldError
                                            message={errors.shipping_address}
                                        />
                                    </div>

                                    <div className="grid gap-x-4 gap-y-4 sm:grid-cols-3">
                                        <div>
                                            <FieldLabel
                                                htmlFor="postal_code"
                                                required
                                            >
                                                {t(
                                                    'buyer.checkout.postal_code',
                                                )}
                                            </FieldLabel>
                                            <input
                                                id="postal_code"
                                                type="text"
                                                inputMode="numeric"
                                                autoComplete="postal-code"
                                                dir="ltr"
                                                value={data.postal_code}
                                                onChange={(e) =>
                                                    setData(
                                                        'postal_code',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="40000"
                                                className={FIELD}
                                            />
                                            <FieldError
                                                message={errors.postal_code}
                                            />
                                        </div>

                                        {/*
                                         * Hidden entirely while the admin has
                                         * destinations switched off: Morocco
                                         * only, flat fee, and the town is
                                         * typed into the address line.
                                         */}
                                        {deliveryShowDestinations && (
                                            <>
                                                {/*
                                                 * Every destination the selected
                                                 * service quotes for. Picking one
                                                 * moves the delivery line in the
                                                 * summary immediately, which is why the
                                                 * rate card is a prop rather than a
                                                 * lookup on the server.
                                                 */}
                                                <div>
                                                    <FieldLabel
                                                        htmlFor="country"
                                                        required
                                                    >
                                                        {t(
                                                            'buyer.checkout.country',
                                                        )}
                                                    </FieldLabel>
                                                    <select
                                                        id="country"
                                                        value={data.country}
                                                        onChange={(e) =>
                                                            chooseCountry(
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={FIELD}
                                                    >
                                                        {rates.countries.map(
                                                            (country) => (
                                                                <option
                                                                    key={
                                                                        country.code
                                                                    }
                                                                    value={
                                                                        country.code
                                                                    }
                                                                >
                                                                    {
                                                                        country.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <FieldError
                                                        message={errors.country}
                                                    />
                                                </div>

                                                {/*
                                                 * Morocco only: `cities` holds Moroccan
                                                 * cities, which is what the selected
                                                 * service prices domestic delivery
                                                 * against, so abroad there is nothing
                                                 * to pick from and the town rides in
                                                 * the address line instead.
                                                 */}
                                                {isDomestic && (
                                                    <div>
                                                        <FieldLabel
                                                            htmlFor="city_id"
                                                            required
                                                        >
                                                            {t(
                                                                'buyer.checkout.city',
                                                            )}
                                                        </FieldLabel>
                                                        <select
                                                            id="city_id"
                                                            value={
                                                                data.city_id ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'city_id',
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className={FIELD}
                                                        >
                                                            {cities.map(
                                                                (city) => (
                                                                    <option
                                                                        key={
                                                                            city.id
                                                                        }
                                                                        value={
                                                                            city.id
                                                                        }
                                                                    >
                                                                        {
                                                                            city.name
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <FieldError
                                                            message={
                                                                errors.city_id
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>

                                    <label
                                        className={`${LABEL_RESET} flex cursor-pointer items-center gap-2.5 text-sm text-souk-brown-deep/80`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.save_address}
                                            onChange={(e) =>
                                                setData(
                                                    'save_address',
                                                    e.target.checked,
                                                )
                                            }
                                            className="h-4 w-4 rounded border-souk-brown/30 text-souk-brown focus:ring-souk-brown/30"
                                        />
                                        {t('buyer.checkout.save_address')}
                                    </label>

                                    {data.save_address && (
                                        <div>
                                            <FieldLabel htmlFor="address_label">
                                                {t(
                                                    'buyer.checkout.address_label',
                                                )}
                                            </FieldLabel>
                                            <input
                                                id="address_label"
                                                type="text"
                                                value={data.address_label}
                                                onChange={(e) =>
                                                    setData(
                                                        'address_label',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'buyer.checkout.address_label_placeholder',
                                                )}
                                                className={FIELD}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="mt-4">
                                <FieldLabel htmlFor="notes">
                                    {t('buyer.checkout.notes')}
                                </FieldLabel>
                                <textarea
                                    id="notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder={t(
                                        'buyer.checkout.notes_placeholder',
                                    )}
                                    className={`${FIELD} resize-y`}
                                />
                            </div>
                        </div>

                        {/* ── Delivery method ──────────────────── */}
                        <div className="mt-10">
                            <h2
                                id="delivery-service-heading"
                                className="font-display text-lg text-souk-title"
                            >
                                {t('buyer.checkout.delivery_service_heading')}
                            </h2>

                            {/*
                             * A radio group like the payment method below: with
                             * one carrier (Amana) it is a single selected
                             * option, and a second carrier — DHL, say — slots
                             * into the same shape with no page change. Picking
                             * a service swaps which rate card the summary
                             * quotes, and the server whitelists the same list
                             * (CheckoutRequest rules on delivery_service_id).
                             */}
                            <fieldset
                                className="mt-4 space-y-2"
                                aria-labelledby="delivery-service-heading"
                            >
                                {deliveryServices.map((service) => {
                                    const active =
                                        data.delivery_service_id === service.id;

                                    return (
                                        <label
                                            key={service.id}
                                            className={`${LABEL_RESET} flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition-colors ${
                                                active
                                                    ? 'border-souk-brown bg-souk-brown/[0.04]'
                                                    : 'border-souk-brown/15 hover:border-souk-brown/40'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="delivery_service"
                                                value={service.id}
                                                checked={active}
                                                onChange={() =>
                                                    setData(
                                                        'delivery_service_id',
                                                        service.id,
                                                    )
                                                }
                                                className="sr-only"
                                            />
                                            <span
                                                aria-hidden
                                                className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors ${
                                                    active
                                                        ? 'border-souk-brown bg-souk-brown text-souk-cream'
                                                        : 'border-souk-brown/30'
                                                }`}
                                            >
                                                {active && (
                                                    <Check
                                                        className="h-2.5 w-2.5"
                                                        strokeWidth={3}
                                                    />
                                                )}
                                            </span>

                                            <span className="min-w-0 flex-1 text-sm">
                                                <span className="flex items-center gap-2 font-medium text-souk-title">
                                                    <Truck
                                                        className="h-4 w-4 shrink-0 text-souk-brown"
                                                        strokeWidth={1.6}
                                                    />
                                                    {service.name}
                                                </span>
                                                {service.description && (
                                                    <span className="mt-1 block text-[13px] leading-relaxed text-souk-brown-deep/70">
                                                        {service.description}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    );
                                })}
                            </fieldset>
                        </div>

                        {/* ── Payment method ────────────────────── */}
                        <div className="mt-10">
                            <h2
                                id="payment-heading"
                                className="font-display text-lg text-souk-title"
                            >
                                {t('buyer.checkout.payment_heading')}
                            </h2>

                            {/*
                             * A one-option radio group rather than a bare
                             * statement: the shape is what the design calls for
                             * and what a second method will slot into, and the
                             * server whitelists the same single value
                             * (CheckoutRequest::PAYMENT_METHODS).
                             *
                             * Named by the heading above rather than by a
                             * visually-hidden <legend> repeating it — two
                             * identical strings would be announced twice.
                             */}
                            <fieldset
                                className="mt-4"
                                aria-labelledby="payment-heading"
                            >
                                <label
                                    className={`${LABEL_RESET} flex cursor-pointer items-start gap-3 rounded-lg border border-souk-brown bg-souk-brown/[0.04] p-3.5`}
                                >
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="cod"
                                        checked={data.payment_method === 'cod'}
                                        onChange={() =>
                                            setData('payment_method', 'cod')
                                        }
                                        className="sr-only"
                                    />
                                    <span
                                        aria-hidden
                                        className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-souk-brown bg-souk-brown text-souk-cream"
                                    >
                                        <Check
                                            className="h-2.5 w-2.5"
                                            strokeWidth={3}
                                        />
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2 text-sm font-medium text-souk-title">
                                            <Banknote
                                                className="h-4 w-4 shrink-0 text-souk-brown"
                                                strokeWidth={1.6}
                                            />
                                            {t('buyer.checkout.payment_cod')}
                                        </span>
                                        <span className="mt-1 block text-[13px] leading-relaxed text-souk-brown-deep/70">
                                            {t('buyer.checkout.cod_note')}
                                        </span>
                                    </span>
                                </label>
                                <FieldError message={errors.payment_method} />
                            </fieldset>
                        </div>
                    </div>

                    {/* ══ Right: order summary ═════════════════════ */}
                    <aside className="lg:sticky lg:top-6 lg:self-start">
                        <SectionHeading>
                            {t('buyer.checkout.summary')}
                        </SectionHeading>

                        <ul className="mt-5 space-y-4">
                            {items.map((line) => (
                                <li key={line.id} className="flex gap-3.5">
                                    <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-souk-brown/5 ring-1 ring-souk-brown/10">
                                        {line.image ? (
                                            <img
                                                src={line.image}
                                                alt={line.name}
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                                                <ZellijStarIcon className="h-5 w-5" />
                                            </div>
                                        )}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm leading-snug text-souk-title">
                                            {line.name}
                                        </p>
                                        {line.variant_label && (
                                            <p className="mt-0.5 truncate text-xs text-souk-brown-deep/65">
                                                {line.variant_label}
                                            </p>
                                        )}
                                        {/* Which workshop ships it — the basket
                                            splits into one parcel each, so this
                                            is what the delivery count counts. */}
                                        <p className="mt-0.5 truncate text-xs text-souk-brown-deep/50">
                                            {line.store_name}
                                        </p>

                                        <div className="mt-1.5 flex items-baseline justify-between gap-2">
                                            <span className="font-display text-sm text-souk-title">
                                                {money(line.line_total ?? 0)}{' '}
                                                <span className="text-xs">
                                                    TTC
                                                </span>
                                            </span>
                                            <span className="text-xs text-souk-brown-deep/60">
                                                {t(
                                                    'buyer.checkout.quantity',
                                                ).replace(
                                                    ':count',
                                                    String(line.quantity),
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {/* ── Discount code ─────────────────────── */}
                        <div className="mt-6 border-t border-souk-brown/12 pt-6">
                            <FieldLabel htmlFor="coupon">
                                {t('buyer.checkout.coupon')}
                            </FieldLabel>
                            <input
                                id="coupon"
                                type="text"
                                value={coupon.data.code}
                                onChange={(e) =>
                                    coupon.setData('code', e.target.value)
                                }
                                placeholder={t(
                                    'buyer.checkout.coupon_placeholder',
                                )}
                                className={FIELD}
                            />

                            {/*
                             * `type="button"` and its own request: this sits
                             * inside the checkout <form>, and a submit button
                             * here would place the order instead of applying a
                             * code. Nesting a second <form> would be invalid
                             * HTML, so the post is made by hand.
                             */}
                            <button
                                type="button"
                                onClick={applyCoupon}
                                disabled={
                                    coupon.processing ||
                                    !coupon.data.code.trim()
                                }
                                data-test="checkout-coupon-apply"
                                className="mt-2.5 inline-flex items-center justify-center rounded-lg bg-souk-title px-5 py-2.5 text-xs font-medium text-white transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {t('buyer.checkout.coupon_apply')}
                            </button>

                            <FieldError message={coupon.errors.code} />
                        </div>

                        {/* ── Totals ────────────────────────────── */}
                        <div className="mt-6 space-y-3 border-t border-souk-brown/12 pt-6 text-sm">
                            <div className="flex items-baseline justify-between">
                                <span className="text-souk-brown-deep/70">
                                    {t('buyer.checkout.products').replace(
                                        ':count',
                                        String(items.length),
                                    )}
                                </span>
                                <span className="text-souk-title">
                                    {money(subtotal)} TTC
                                </span>
                            </div>

                            <div className="flex items-baseline justify-between">
                                <span className="text-souk-brown-deep/70">
                                    {t('buyer.checkout.delivery_fee')}
                                </span>
                                <span className="text-souk-title">
                                    {money(deliveryTotal)} TTC
                                </span>
                            </div>
                        </div>

                        <div className="mt-5 flex items-baseline justify-between border-t border-souk-brown/12 pt-5">
                            <span className="font-medium text-souk-title">
                                {t('buyer.checkout.total')}
                            </span>
                            <span className="font-display text-2xl leading-none text-souk-title">
                                {money(grandTotal)}
                                <span className="ms-1.5 text-sm">TTC</span>
                            </span>
                        </div>

                        {/*
                         * The one place the converted figure is not enough.
                         * Everything above is indicative; the courier collects
                         * dirhams, so the actual amount has to be stated here
                         * rather than left for the buyer to discover at the
                         * door. Hidden for anyone already reading MAD, where
                         * it would just repeat the line above it.
                         */}
                        {!isBase && (
                            <p className="mt-3 rounded-lg bg-souk-brown/5 px-3 py-2.5 text-xs leading-relaxed text-souk-brown-deep/75">
                                {t('common.currency_note').replace(
                                    ':currency',
                                    currency.code,
                                )}
                                <span className="mt-1 block font-medium text-souk-title">
                                    {t('common.currency_charged').replace(
                                        ':amount',
                                        baseMoney(grandTotal),
                                    )}
                                </span>
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            data-test="checkout-submit"
                            className="mt-6 inline-flex w-full items-center justify-center gap-2.5 rounded-lg bg-souk-title px-6 py-3.5 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? t('buyer.checkout.processing')
                                : t('buyer.checkout.confirm')}
                        </button>
                    </aside>
                </form>
            </section>

            <SiteFooter />
        </div>
    );
};

export default Checkout;
