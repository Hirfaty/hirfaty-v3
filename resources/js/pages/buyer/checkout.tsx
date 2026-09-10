import { Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    MapPin,
    Phone,
    Plus,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/** Flat delivery charge, once per workshop — see the cart page. */
const AMANA_FEE = 20;

/** Shared by every text input on this page. */
const FIELD =
    'w-full rounded-xl border border-souk-brown/20 bg-white/70 px-4 py-3 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown focus:outline-none';

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
    is_default: boolean;
};

type City = { id: number; name: string };

/** Sentinel for the "type a new address" radio, so one control drives both. */
const NEW_ADDRESS = 'new';

/**
 * Declared rather than inferred: `address_id` and `city_id` are genuinely
 * nullable (an empty address book, no cities loaded), and letting useForm
 * infer them from the initial values narrows both to `number`, which then
 * rejects clearing them when the buyer switches to a new address.
 */
type CheckoutForm = {
    address_id: number | null;
    shipping_address: string;
    city_id: number | null;
    shipping_phone: string;
    save_address: boolean;
    address_label: string;
    notes: string;
};

function FieldLabel({
    children,
    optional,
}: {
    children: React.ReactNode;
    optional?: string;
}) {
    return (
        <label className={`mb-2 block text-souk-brown-deep/55 ${EYEBROW}`}>
            {children}
            {optional && (
                <span className="ms-1.5 tracking-normal normal-case">
                    ({optional})
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
    accountPhone,
}: {
    items: CheckoutLine[];
    cities: City[];
    addresses: SavedAddress[];
    accountPhone: string | null;
}) => {
    const { t, locale } = useTranslation();

    // Default first (the query orders on is_default), so a buyer with a book
    // lands on their usual address rather than an empty form.
    const preselected = addresses.find((a) => a.is_default) ?? addresses[0];

    const [choice, setChoice] = useState<number | typeof NEW_ADDRESS>(
        preselected ? preselected.id : NEW_ADDRESS,
    );

    const { data, setData, post, processing, errors } = useForm<CheckoutForm>({
        address_id: preselected?.id ?? null,
        shipping_address: '',
        city_id: cities[0]?.id ?? null,
        /*
         * Pre-filled but always editable. A saved address may predate the
         * phone being mandatory (the column is nullable), so this falls back
         * to the number on the account rather than assuming the address has
         * one — and the server requires it either way.
         */
        shipping_phone: preselected?.recipient_phone ?? accountPhone ?? '',
        save_address: false,
        address_label: '',
        notes: '',
    });

    /*
     * `cart` is a whole-basket rejection (empty cart, a line that went out of
     * stock or lost its price mid-checkout), not a field error — the server
     * raises it via withErrors, so it never appears in the form's own typed
     * error keys.
     */
    const cartError = (errors as Record<string, string | undefined>).cart;

    const money = (value: number) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);

    const chooseSaved = (address: SavedAddress) => {
        setChoice(address.id);
        setData((current) => ({
            ...current,
            address_id: address.id,
            // Cleared so a half-typed new address can't ride along and be
            // saved to the book behind the buyer's back.
            shipping_address: '',
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
            shipping_phone: current.shipping_phone || (accountPhone ?? ''),
        }));
    };

    // Grouped by workshop: checkout splits the basket into one order each, and
    // the buyer receives that many parcels.
    const grouped = items.reduce<Record<string, CheckoutLine[]>>(
        (acc, line) => {
            const key = String(line.store_id);
            (acc[key] ??= []).push(line);

            return acc;
        },
        {},
    );

    const storeCount = Object.keys(grouped).length;
    const productsTotal = items.reduce(
        (sum, line) => sum + (line.line_total ?? 0),
        0,
    );
    const deliveryTotal = AMANA_FEE * storeCount;
    const grandTotal = productsTotal + deliveryTotal;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            <section className="container pt-10 pb-6">
                <Link
                    href={route('cart.show')}
                    className={`inline-flex items-center gap-2 text-souk-brown-deep/60 transition-colors hover:text-souk-title ${EYEBROW}`}
                >
                    <ArrowLeft className="h-3.5 w-3.5" strokeWidth={1.6} />
                    {t('buyer.checkout.back')}
                </Link>

                <h1 className="mt-5 font-display text-[clamp(1.9rem,3.4vw,2.9rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                    {t('buyer.checkout.title')}
                </h1>
            </section>

            <section className="container pb-24">
                {cartError && (
                    <div className="mb-8 flex items-start gap-3 rounded-xl border border-terracotta/25 bg-terracotta/5 px-4 py-3.5 text-sm text-terracotta">
                        <AlertCircle
                            className="mt-0.5 h-4 w-4 shrink-0"
                            strokeWidth={1.6}
                        />
                        <p>{cartError}</p>
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="grid grid-cols-12 gap-8 lg:gap-12"
                >
                    {/* ── Delivery ──────────────────────────────── */}
                    <div className="col-span-12 space-y-10 lg:col-span-7">
                        <div>
                            <h2 className="border-b border-souk-brown/12 pb-3 font-display text-xl text-souk-title">
                                {t('buyer.checkout.delivery_heading')}
                            </h2>

                            {/* Saved addresses, when the buyer has any. */}
                            {addresses.length > 0 && (
                                <div className="mt-5">
                                    <p
                                        className={`text-souk-brown-deep/55 ${EYEBROW}`}
                                    >
                                        {t('buyer.checkout.saved_addresses')}
                                    </p>

                                    <div className="mt-3 space-y-2.5">
                                        {addresses.map((address) => {
                                            const active =
                                                choice === address.id;

                                            return (
                                                <button
                                                    key={address.id}
                                                    type="button"
                                                    onClick={() =>
                                                        chooseSaved(address)
                                                    }
                                                    aria-pressed={active}
                                                    className={`flex w-full items-start gap-3 rounded-xl border p-4 text-start transition-colors ${
                                                        active
                                                            ? 'border-souk-brown bg-souk-brown/5'
                                                            : 'border-souk-brown/15 hover:border-souk-brown/40'
                                                    }`}
                                                >
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

                                                    <span className="min-w-0 flex-1">
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

                                                        <span className="mt-1 flex items-start gap-1.5 text-[13px] leading-relaxed text-souk-brown-deep/75">
                                                            <MapPin
                                                                className="mt-0.5 h-3 w-3 shrink-0"
                                                                strokeWidth={
                                                                    1.6
                                                                }
                                                            />
                                                            {
                                                                address.address_line
                                                            }
                                                            {address.city_name &&
                                                                `, ${address.city_name}`}
                                                        </span>

                                                        {address.recipient_phone && (
                                                            <span className="mt-1 flex items-center gap-1.5 text-[13px] text-souk-brown-deep/60">
                                                                <Phone
                                                                    className="h-3 w-3 shrink-0"
                                                                    strokeWidth={
                                                                        1.6
                                                                    }
                                                                />
                                                                {
                                                                    address.recipient_phone
                                                                }
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            );
                                        })}

                                        <button
                                            type="button"
                                            onClick={chooseNew}
                                            aria-pressed={
                                                choice === NEW_ADDRESS
                                            }
                                            className={`flex w-full items-center gap-3 rounded-xl border border-dashed p-4 text-start transition-colors ${
                                                choice === NEW_ADDRESS
                                                    ? 'border-souk-brown bg-souk-brown/5 text-souk-title'
                                                    : 'border-souk-brown/25 text-souk-brown-deep/70 hover:border-souk-brown/50'
                                            }`}
                                        >
                                            <Plus
                                                className="h-4 w-4 shrink-0"
                                                strokeWidth={1.6}
                                            />
                                            <span className="text-sm font-medium">
                                                {t(
                                                    'buyer.checkout.use_new_address',
                                                )}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* New address form. Always shown to a buyer with
                                an empty address book. */}
                            {choice === NEW_ADDRESS && (
                                <div className="mt-6 space-y-4">
                                    {addresses.length > 0 && (
                                        <p
                                            className={`text-souk-brown-deep/55 ${EYEBROW}`}
                                        >
                                            {t('buyer.checkout.new_address')}
                                        </p>
                                    )}

                                    <div>
                                        <FieldLabel>
                                            {t('buyer.checkout.city')}
                                        </FieldLabel>
                                        <select
                                            value={data.city_id ?? ''}
                                            onChange={(e) =>
                                                setData(
                                                    'city_id',
                                                    Number(e.target.value),
                                                )
                                            }
                                            className={FIELD}
                                        >
                                            {cities.map((city) => (
                                                <option
                                                    key={city.id}
                                                    value={city.id}
                                                >
                                                    {city.name}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={errors.city_id} />
                                    </div>

                                    <div>
                                        <FieldLabel>
                                            {t('buyer.checkout.address_line')}
                                        </FieldLabel>
                                        <textarea
                                            value={data.shipping_address}
                                            onChange={(e) =>
                                                setData(
                                                    'shipping_address',
                                                    e.target.value,
                                                )
                                            }
                                            rows={3}
                                            placeholder={t(
                                                'buyer.checkout.address_placeholder',
                                            )}
                                            className={FIELD}
                                        />
                                        <FieldError
                                            message={errors.shipping_address}
                                        />
                                    </div>

                                    <label className="flex cursor-pointer items-center gap-2.5 text-sm text-souk-brown-deep/80">
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
                                            <FieldLabel
                                                optional={t(
                                                    'buyer.checkout.optional',
                                                )}
                                            >
                                                {t(
                                                    'buyer.checkout.address_label',
                                                )}
                                            </FieldLabel>
                                            <input
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

                            {/*
                             * Outside the branch above: the courier needs a
                             * number whichever address was chosen, and a saved
                             * one may not carry it.
                             */}
                            <div className="mt-6">
                                <FieldLabel>
                                    {t('buyer.checkout.phone')}
                                </FieldLabel>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    required
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
                                <p className="mt-1.5 flex items-center gap-1.5 text-xs text-souk-brown-deep/60">
                                    <Truck
                                        className="h-3 w-3 shrink-0"
                                        strokeWidth={1.6}
                                    />
                                    {t('buyer.checkout.phone_hint')}
                                </p>
                                <FieldError message={errors.shipping_phone} />
                            </div>

                            <div className="mt-5">
                                <FieldLabel
                                    optional={t('buyer.checkout.optional')}
                                >
                                    {t('buyer.checkout.notes')}
                                </FieldLabel>
                                <textarea
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    rows={2}
                                    placeholder={t(
                                        'buyer.checkout.notes_placeholder',
                                    )}
                                    className={FIELD}
                                />
                            </div>
                        </div>

                        {/* ── Shipments ─────────────────────────── */}
                        <div>
                            <h2 className="border-b border-souk-brown/12 pb-3 font-display text-xl text-souk-title">
                                {t('buyer.checkout.shipments_heading')}
                            </h2>

                            <p className="mt-4 text-[13px] leading-relaxed text-souk-brown-deep/70">
                                {(storeCount > 1
                                    ? t('buyer.checkout.shipments_note_many')
                                    : t('buyer.checkout.shipments_note_one')
                                ).replaceAll(':count', String(storeCount))}
                            </p>

                            <div className="mt-5 space-y-4">
                                {Object.entries(grouped).map(
                                    ([storeId, lines]) => (
                                        <div
                                            key={storeId}
                                            className="rounded-2xl border border-souk-brown/12 bg-souk-peach/30 p-5"
                                        >
                                            <p
                                                className={`text-souk-brown ${EYEBROW}`}
                                            >
                                                {t(
                                                    'buyer.checkout.shipped_by',
                                                ).replace(
                                                    ':store',
                                                    lines[0].store_name,
                                                )}
                                            </p>

                                            <ul className="mt-4 space-y-3">
                                                {lines.map((line) => (
                                                    <li
                                                        key={line.id}
                                                        className="flex items-center gap-3"
                                                    >
                                                        <div className="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                                                            {line.image ? (
                                                                <img
                                                                    src={
                                                                        line.image
                                                                    }
                                                                    alt={
                                                                        line.name
                                                                    }
                                                                    loading="lazy"
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                                                                    <ZellijStarIcon className="h-4 w-4" />
                                                                </div>
                                                            )}
                                                        </div>

                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm text-souk-title">
                                                                {line.name}
                                                            </p>
                                                            {line.variant_label && (
                                                                <p className="truncate text-xs text-souk-brown-deep/70">
                                                                    {
                                                                        line.variant_label
                                                                    }
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-souk-brown-deep/55">
                                                                {t(
                                                                    'buyer.checkout.quantity',
                                                                ).replace(
                                                                    ':count',
                                                                    String(
                                                                        line.quantity,
                                                                    ),
                                                                )}
                                                            </p>
                                                        </div>

                                                        <span className="font-display text-sm text-souk-title">
                                                            {money(
                                                                line.line_total ??
                                                                    0,
                                                            )}{' '}
                                                            MAD
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>

                                            <div className="mt-4 flex items-center justify-between border-t border-souk-brown/10 pt-3 text-xs text-souk-brown-deep/65">
                                                <span className="flex items-center gap-1.5">
                                                    <Truck
                                                        className="h-3 w-3"
                                                        strokeWidth={1.6}
                                                    />
                                                    {t(
                                                        'buyer.checkout.amana_standard',
                                                    )}
                                                </span>
                                                <span>
                                                    {money(AMANA_FEE)} MAD
                                                </span>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ── Summary ───────────────────────────────── */}
                    <aside className="col-span-12 lg:col-span-5">
                        <div className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 p-6 lg:sticky lg:top-6">
                            <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                                {t('buyer.checkout.summary')}
                            </p>

                            <div className="mt-5 space-y-3 text-sm">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-souk-brown-deep/75">
                                        {t('buyer.checkout.products').replace(
                                            ':count',
                                            String(items.length),
                                        )}
                                    </span>
                                    <span className="text-souk-title">
                                        {money(productsTotal)} MAD
                                    </span>
                                </div>

                                <div className="flex items-baseline justify-between">
                                    <span className="text-souk-brown-deep/75">
                                        {t(
                                            'buyer.checkout.delivery_fee',
                                        ).replace(':count', String(storeCount))}
                                    </span>
                                    <span className="text-souk-title">
                                        {money(deliveryTotal)} MAD
                                    </span>
                                </div>
                            </div>

                            <div className="my-5 h-px bg-souk-brown/12" />

                            <div className="flex items-baseline justify-between">
                                <span className="text-souk-title">
                                    {t('buyer.checkout.total')}
                                </span>
                                <span className="font-display text-2xl leading-none text-souk-title">
                                    {money(grandTotal)}
                                    <span className="ms-1.5 text-sm">MAD</span>
                                </span>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-6 inline-flex w-full items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing
                                    ? t('buyer.checkout.processing')
                                    : `${t('buyer.checkout.confirm')} · ${money(grandTotal)} MAD`}
                            </button>

                            <p className="mt-5 flex items-start gap-2.5 text-[12px] leading-relaxed text-souk-brown-deep/70">
                                <ShieldCheck
                                    className="mt-0.5 h-3.5 w-3.5 shrink-0 text-souk-brown"
                                    strokeWidth={1.5}
                                />
                                {t('buyer.checkout.cod_note')}
                            </p>
                        </div>
                    </aside>
                </form>
            </section>

            <SiteFooter />
        </div>
    );
};

export default Checkout;
