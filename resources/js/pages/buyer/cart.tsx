import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Minus,
    Package,
    Plus,
    ShieldCheck,
    ShoppingBag,
    Trash2,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/** Museum-caption label, matching the listing and detail pages. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

/**
 * Flat delivery charge, once per workshop.
 *
 * Checkout splits one basket into an order per store and bills Amana on each,
 * so the basket has to count stores — not items — or it quotes a total the
 * next screen contradicts. This page used to charge 20 per *unit*, which on a
 * five-of-one-thing basket over-quoted the delivery fivefold.
 */
const AMANA_FEE = 20;

/** One basket line, already priced for this viewer by CartLineResource. */
type CartLine = {
    id: number;
    product_id: number;
    name: string;
    slug: string | null;
    store_id: number | null;
    store_name: string;
    image: string | null;
    variant_label: string | null;
    quantity: number;
    /** Null when the piece has no agreed price yet — checkout refuses it. */
    unit_price: number | null;
    line_total: number | null;
    original_unit_price: number | null;
    available_stock: number;
    min_quantity: number;
};

/**
 * Line thumbnail. Falls back to the zellij mark rather than an empty frame,
 * so a shop that hasn't uploaded a photo still reads as a product.
 */
function Thumbnail({ line }: { line: CartLine }) {
    return (
        <div className="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-souk-brown/5 ring-1 ring-souk-brown/10 sm:h-28 sm:w-28">
            {line.image ? (
                <img
                    src={line.image}
                    alt={line.name}
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
            ) : (
                <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                    <ZellijStarIcon className="h-7 w-7" />
                </div>
            )}
        </div>
    );
}

const CartPage = ({ items }: { items: CartLine[] }) => {
    const { t, locale } = useTranslation();
    const [busyId, setBusyId] = useState<number | null>(null);

    const money = (value: number) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);

    const count = items.reduce((sum, line) => sum + line.quantity, 0);

    const subtotal = items.reduce(
        (sum, line) => sum + (line.line_total ?? 0),
        0,
    );

    const savings = items.reduce((sum, line) => {
        if (line.original_unit_price === null || line.unit_price === null) {
            return sum;
        }

        return (
            sum + (line.original_unit_price - line.unit_price) * line.quantity
        );
    }, 0);

    // One Amana fee per workshop, mirroring how checkout splits the basket.
    const storeCount = new Set(items.map((line) => line.store_id)).size;
    const delivery = AMANA_FEE * storeCount;
    const total = subtotal + delivery;

    /*
     * An admin can publish a piece before its price is agreed. Checkout
     * refuses the whole basket in that case, so the button is held shut here
     * with the offending line called out — rather than letting the buyer reach
     * the till and be turned away with no explanation.
     */
    const hasUnpriced = items.some((line) => line.unit_price === null);

    const changeQuantity = (line: CartLine, quantity: number) => {
        setBusyId(line.id);
        router.patch(
            route('cart.update', line.id),
            { quantity },
            {
                preserveScroll: true,
                // The server still refuses on a stock race or a trade minimum;
                // say which, instead of silently snapping back.
                onError: (errors) =>
                    toast.error(Object.values(errors)[0] as string),
                onFinish: () => setBusyId(null),
            },
        );
    };

    const removeLine = (line: CartLine) => {
        setBusyId(line.id);
        router.delete(route('cart.remove', line.id), {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            <section className="container pt-10 pb-6">
                <Link
                    href={route('products.index')}
                    className={`inline-flex items-center gap-2 text-souk-brown-deep/60 transition-colors hover:text-souk-title ${EYEBROW}`}
                >
                    <ArrowLeft className="h-3.5 w-3.5" strokeWidth={1.6} />
                    {t('buyer.cart.back')}
                </Link>

                <div className="mt-5 flex flex-wrap items-baseline justify-between gap-3">
                    <h1 className="font-display text-[clamp(1.9rem,3.4vw,2.9rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                        {t('buyer.cart.title')}
                    </h1>
                    {items.length > 0 && (
                        <span className={`text-souk-brown ${EYEBROW}`}>
                            {(count > 1
                                ? t('buyer.cart.count_many')
                                : t('buyer.cart.count_one')
                            ).replace(':count', String(count))}
                        </span>
                    )}
                </div>
            </section>

            {items.length === 0 ? (
                <section className="container pb-24">
                    <div className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 px-6 py-16 text-center">
                        <ShoppingBag
                            className="mx-auto h-9 w-9 text-souk-brown/40"
                            strokeWidth={1.3}
                        />
                        <p className="mt-5 font-display text-2xl text-souk-title">
                            {t('buyer.cart.empty_title')}
                        </p>
                        <p className="mx-auto mt-2 max-w-sm text-[15px] leading-relaxed text-souk-brown-deep/70">
                            {t('buyer.cart.empty_body')}
                        </p>
                        <Link
                            href={route('products.index')}
                            className="mt-7 inline-flex items-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.cart.empty_cta')}
                            <ShoppingBag
                                className="h-4 w-4"
                                strokeWidth={1.6}
                            />
                        </Link>
                    </div>
                </section>
            ) : (
                <section className="container grid grid-cols-12 gap-8 pb-20 lg:gap-12">
                    {/* ── Lines ─────────────────────────────────── */}
                    <div className="col-span-12 lg:col-span-8">
                        <ul className="space-y-4">
                            {items.map((line) => {
                                const busy = busyId === line.id;
                                const atFloor =
                                    line.quantity <= line.min_quantity;
                                const atCeiling =
                                    line.quantity >= line.available_stock;

                                return (
                                    <li
                                        key={line.id}
                                        className="rounded-2xl border border-souk-brown/12 bg-white/60 p-4 transition-colors hover:border-souk-brown/25 sm:p-5"
                                    >
                                        <div className="flex gap-4 sm:gap-5">
                                            {line.slug ? (
                                                <Link
                                                    href={route(
                                                        'products.show',
                                                        line.slug,
                                                    )}
                                                >
                                                    <Thumbnail line={line} />
                                                </Link>
                                            ) : (
                                                <Thumbnail line={line} />
                                            )}

                                            <div className="flex min-w-0 flex-1 flex-col">
                                                <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                                    <div className="min-w-0">
                                                        {line.slug ? (
                                                            <Link
                                                                href={route(
                                                                    'products.show',
                                                                    line.slug,
                                                                )}
                                                                className="font-display text-lg leading-snug text-souk-title transition-colors hover:text-souk-brown"
                                                            >
                                                                {line.name}
                                                            </Link>
                                                        ) : (
                                                            <span className="font-display text-lg leading-snug text-souk-title">
                                                                {line.name}
                                                            </span>
                                                        )}

                                                        {/* Without this, two
                                                            variants of one
                                                            product read as two
                                                            identical lines. */}
                                                        {line.variant_label && (
                                                            <p className="mt-0.5 text-sm text-souk-brown-deep/80">
                                                                {
                                                                    line.variant_label
                                                                }
                                                            </p>
                                                        )}

                                                        <p className="mt-0.5 text-xs text-souk-brown-deep/60">
                                                            {t(
                                                                'buyer.cart.sold_by',
                                                            ).replace(
                                                                ':store',
                                                                line.store_name,
                                                            )}
                                                        </p>
                                                    </div>

                                                    {/* ms-auto so the figure
                                                        stays flush to the end
                                                        even once it wraps under
                                                        the title on a narrow
                                                        screen. */}
                                                    <div className="ms-auto text-end">
                                                        {line.line_total ===
                                                        null ? (
                                                            <span className="font-display text-lg text-terracotta">
                                                                —
                                                            </span>
                                                        ) : (
                                                            <>
                                                                <p className="font-display text-xl leading-none text-souk-title">
                                                                    {money(
                                                                        line.line_total,
                                                                    )}
                                                                    <span className="ms-1 text-xs">
                                                                        MAD
                                                                    </span>
                                                                </p>
                                                                <p className="mt-1 text-[11px] text-souk-brown-deep/55">
                                                                    {line.original_unit_price !==
                                                                        null && (
                                                                        <span className="me-1.5 line-through">
                                                                            {money(
                                                                                line.original_unit_price,
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                    {money(
                                                                        line.unit_price ??
                                                                            0,
                                                                    )}{' '}
                                                                    MAD{' '}
                                                                    {t(
                                                                        'buyer.cart.per_unit',
                                                                    )}
                                                                </p>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>

                                                {line.unit_price === null && (
                                                    <p className="mt-3 rounded-xl bg-terracotta/8 px-3 py-2 text-xs leading-relaxed text-terracotta">
                                                        {t(
                                                            'buyer.cart.unpriced',
                                                        )}
                                                    </p>
                                                )}

                                                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                                                    <div className="inline-flex items-center rounded-xl border border-souk-brown/20">
                                                        <button
                                                            onClick={() =>
                                                                changeQuantity(
                                                                    line,
                                                                    line.quantity -
                                                                        1,
                                                                )
                                                            }
                                                            disabled={
                                                                busy || atFloor
                                                            }
                                                            aria-label={t(
                                                                'buyer.cart.decrease',
                                                            )}
                                                            className="rounded-s-xl px-3 py-2 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                                                        >
                                                            <Minus className="h-3 w-3" />
                                                        </button>
                                                        <span className="min-w-9 px-1 text-center text-sm text-souk-title tabular-nums">
                                                            {busy ? (
                                                                <Spinner className="mx-auto h-3.5 w-3.5 text-souk-brown" />
                                                            ) : (
                                                                line.quantity
                                                            )}
                                                        </span>
                                                        <button
                                                            onClick={() =>
                                                                changeQuantity(
                                                                    line,
                                                                    line.quantity +
                                                                        1,
                                                                )
                                                            }
                                                            disabled={
                                                                busy ||
                                                                atCeiling
                                                            }
                                                            aria-label={t(
                                                                'buyer.cart.increase',
                                                            )}
                                                            className="rounded-e-xl px-3 py-2 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                                                        >
                                                            <Plus className="h-3 w-3" />
                                                        </button>
                                                    </div>

                                                    <button
                                                        onClick={() =>
                                                            removeLine(line)
                                                        }
                                                        disabled={busy}
                                                        className="inline-flex items-center gap-1.5 text-xs text-souk-brown-deep/65 transition-colors hover:text-terracotta disabled:opacity-50"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                        {t('buyer.cart.remove')}
                                                    </button>

                                                    {/* Only worth saying while
                                                        it constrains them. */}
                                                    {line.min_quantity > 1 && (
                                                        <span className="text-[11px] text-olive">
                                                            {t(
                                                                'buyer.cart.min_qty',
                                                            ).replace(
                                                                ':count',
                                                                String(
                                                                    line.min_quantity,
                                                                ),
                                                            )}
                                                        </span>
                                                    )}
                                                    {atCeiling && (
                                                        <span className="text-[11px] text-terracotta">
                                                            {t(
                                                                'buyer.cart.stock_left',
                                                            ).replace(
                                                                ':count',
                                                                String(
                                                                    line.available_stock,
                                                                ),
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <p className="mt-6 flex items-start gap-2.5 text-[13px] leading-relaxed text-souk-brown-deep/70">
                            <Package
                                className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                                strokeWidth={1.5}
                            />
                            {t('buyer.cart.split_note')}
                        </p>
                    </div>

                    {/* ── Summary ───────────────────────────────── */}
                    <aside className="col-span-12 lg:col-span-4">
                        <div className="rounded-2xl border border-souk-brown/12 bg-souk-peach/40 p-6 lg:sticky lg:top-6">
                            <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                                {t('buyer.cart.summary')}
                            </p>

                            <div className="mt-5 space-y-3 text-sm">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-souk-brown-deep/75">
                                        {t('buyer.cart.subtotal')}
                                    </span>
                                    <span className="text-souk-title">
                                        {money(subtotal)} MAD
                                    </span>
                                </div>

                                {savings > 0 && (
                                    <div className="flex items-baseline justify-between text-terracotta">
                                        <span>{t('buyer.cart.savings')}</span>
                                        <span>−{money(savings)} MAD</span>
                                    </div>
                                )}

                                <div className="flex items-baseline justify-between">
                                    <span className="text-souk-brown-deep/75">
                                        {t('buyer.cart.delivery')}
                                        <span className="ms-1.5 text-[11px] text-souk-brown-deep/50">
                                            (
                                            {(storeCount > 1
                                                ? t(
                                                      'buyer.cart.delivery_many_stores',
                                                  )
                                                : t(
                                                      'buyer.cart.delivery_one_store',
                                                  )
                                            ).replace(
                                                ':count',
                                                String(storeCount),
                                            )}
                                            )
                                        </span>
                                    </span>
                                    <span className="text-souk-title">
                                        {money(delivery)} MAD
                                    </span>
                                </div>
                            </div>

                            <div className="my-5 h-px bg-souk-brown/12" />

                            <div className="flex items-baseline justify-between">
                                <span className="text-souk-title">
                                    {t('buyer.cart.total')}
                                </span>
                                <span className="font-display text-2xl leading-none text-souk-title">
                                    {money(total)}
                                    <span className="ms-1.5 text-sm">MAD</span>
                                </span>
                            </div>

                            <Link
                                href={route('checkout.show')}
                                // An unpriced line makes the whole basket
                                // unpayable, so the link is inert rather than
                                // sending them to a checkout that will refuse.
                                aria-disabled={hasUnpriced}
                                onClick={(e) =>
                                    hasUnpriced && e.preventDefault()
                                }
                                className={`mt-6 inline-flex w-full items-center justify-center gap-2.5 rounded-xl px-6 py-3.5 text-sm font-medium transition-colors ${
                                    hasUnpriced
                                        ? 'pointer-events-none bg-souk-brown/40 text-souk-cream/70'
                                        : 'bg-souk-brown text-souk-cream hover:bg-souk-brown-deep'
                                }`}
                            >
                                {t('buyer.cart.checkout')}
                                <ShoppingBag
                                    className="h-4 w-4"
                                    strokeWidth={1.6}
                                />
                            </Link>

                            <div className="mt-5 space-y-2.5 text-[12px] text-souk-brown-deep/65">
                                <p className="flex items-center gap-2">
                                    <ShieldCheck
                                        className="h-3.5 w-3.5 shrink-0 text-souk-brown"
                                        strokeWidth={1.5}
                                    />
                                    {t('buyer.cart.secure_payment')}
                                </p>
                                <p className="flex items-center gap-2">
                                    <Truck
                                        className="h-3.5 w-3.5 shrink-0 text-souk-brown"
                                        strokeWidth={1.5}
                                    />
                                    {t('buyer.detail.delivery')}
                                </p>
                            </div>
                        </div>
                    </aside>
                </section>
            )}

            <SiteFooter />
        </div>
    );
};

export default CartPage;
