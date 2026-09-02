import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgePercent,
    CheckCircle2,
    Clock,
    Hand,
    Heart,
    Home,
    Leaf,
    MapPin,
    Minus,
    Package,
    Play,
    Plus,
    ShieldCheck,
    ShoppingBag,
    Store as StoreIcon,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import { useGuestCart } from '@/hooks/useGuestCart';
import type { Media } from '@/types';
import type {
    BuyerProductResource,
    ProductVariantResource,
} from '@/types/extended_models';

/** Museum-caption label, matching the listing page. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

const LOCALE_TAGS: Record<string, string> = {
    fr: 'fr-MA',
    ar: 'ar-MA',
    en: 'en-MA',
};

type DetailProduct = BuyerProductResource & {
    wishlists_count?: number;
    professional_effective_price?: number | null;
    min_order_qty_professional?: number;
    store: BuyerProductResource['store'] & { logo?: string | null };
};

type Selection = Record<string, string>;

/**
 * Does this variant match every option named in `selection`?
 *
 * Keys absent from `selection` are ignored, which is what makes it usable for
 * partial matches while the shopper is still mid-choice.
 */
function matches(variant: ProductVariantResource, selection: Selection) {
    return Object.entries(selection).every(
        ([name, value]) =>
            variant.options.find((o) => o.name === name)?.value === value,
    );
}

/**
 * The variant the current selection identifies, or null while it is still
 * ambiguous (so the Add-to-Cart button stays disabled).
 *
 * Matched against each variant's *own* options rather than against the
 * product's full list of option groups. Those are not always the same thing:
 * nothing stops a seller giving one variant a "Couleur" and another a
 * "Taille", in which case no variant carries every group and requiring all of
 * them would leave the product permanently unbuyable. Comparing sizes as well
 * as values keeps a partial choice (Couleur only, on a Couleur+Taille product)
 * correctly unresolved.
 */
function resolveVariant(
    variants: ProductVariantResource[],
    selection: Selection,
): ProductVariantResource | null {
    const chosen = Object.keys(selection).length;

    return (
        variants.find(
            (v) => v.options.length === chosen && matches(v, selection),
        ) ?? null
    );
}

/**
 * Availability-aware option chip.
 *
 * A value is "reachable" when some in-stock variant carries it *together with*
 * whatever is chosen in the other groups — so picking Rouge greys out the
 * sizes Rouge doesn't come in, rather than letting the shopper build a
 * combination that does not exist and only learn at the Add-to-Cart button.
 */
function OptionValue({
    label,
    selected,
    reachable,
    onSelect,
}: {
    label: string;
    selected: boolean;
    reachable: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={`rounded-xl border px-4 py-2.5 text-sm transition-all ${
                selected
                    ? 'border-souk-brown bg-souk-brown/5 text-souk-brown'
                    : 'border-souk-brown/20 text-souk-brown-deep hover:border-souk-brown/50'
            } ${reachable ? '' : 'text-souk-brown-deep/35 line-through'}`}
        >
            {label}
        </button>
    );
}

/**
 * Price block.
 *
 * A verified trade buyer sees their wholesale rate as *the* price, with the
 * public price struck through — CheckoutService bills exactly this figure, so
 * it can be stated plainly rather than hedged as "indicative". Everyone else
 * sees retail. Both come from the same `Product::audienceFor()` decision
 * server-side, so this cannot quote a number the basket won't honour.
 *
 * `variant` wins when one is selected: its override is what gets billed.
 */
function PriceBlock({
    product,
    variant,
    money,
    t,
}: {
    product: DetailProduct;
    variant: ProductVariantResource | null;
    money: (v: number | string) => string;
    t: (k: string) => string;
}) {
    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const productRetail = sale !== null && sale < base ? sale : base;

    // The variant's resolved price already accounts for the viewer's price
    // list, so it replaces the product figure rather than adjusting it.
    const retail =
        variant?.price != null ? Number(variant.price) : productRetail;
    const strike =
        variant !== null
            ? variant.original_price != null
                ? Number(variant.original_price)
                : null
            : sale !== null && sale < base
              ? base
              : null;

    const trade = product.professional_effective_price ?? null;
    const hasTrade = variant === null && trade !== null && trade < retail;
    const saving = hasTrade ? Math.round((1 - trade / retail) * 100) : 0;
    const minQty = product.min_order_qty_professional ?? 1;

    if (hasTrade) {
        return (
            <div className="rounded-2xl border border-olive/25 bg-olive/5 p-5">
                <p className={`text-olive/80 ${EYEBROW}`}>
                    {t('buyer.detail.your_trade_price')}
                </p>
                <div className="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span className="font-display text-4xl leading-none text-olive">
                        {money(trade)}
                        <span className="ms-1.5 text-base">MAD</span>
                    </span>
                    <span className="text-sm text-souk-brown-deep/50 line-through">
                        {money(retail)} MAD
                    </span>
                    {saving > 0 && (
                        <span className="rounded-full bg-olive px-2 py-0.5 text-[11px] font-medium text-white">
                            −{saving}%
                        </span>
                    )}
                </div>
                <p className="mt-3 flex items-start gap-2 text-xs leading-relaxed text-souk-brown-deep/70">
                    <BadgePercent
                        className="mt-0.5 h-3.5 w-3.5 shrink-0 text-olive"
                        strokeWidth={1.5}
                    />
                    {t('buyer.detail.trade_billed_note')}
                    {minQty > 1 && (
                        <>
                            {' · '}
                            {t('buyer.detail.trade_min_qty').replace(
                                ':count',
                                String(minQty),
                            )}
                        </>
                    )}
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-baseline gap-x-3">
            <span
                className={`font-display text-[2.6rem] leading-none ${strike ? 'text-terracotta' : 'text-souk-title'}`}
            >
                {money(retail)}
                <span className="ms-1.5 text-lg">MAD</span>
            </span>
            {strike && (
                <span className="text-base text-souk-brown-deep/45 line-through">
                    {money(strike)} MAD
                </span>
            )}
        </div>
    );
}

/** The four reassurance columns under the buy buttons. */
function TrustRow({ t }: { t: (k: string) => string }) {
    const items = [
        { icon: Leaf, key: 'materials' },
        { icon: Hand, key: 'handmade' },
        { icon: ShieldCheck, key: 'durable' },
        { icon: Package, key: 'packaging' },
    ];

    return (
        <div className="mt-8 grid grid-cols-2 gap-x-4 gap-y-6 border-y border-souk-brown/12 py-7 sm:grid-cols-4">
            {items.map(({ icon: Icon, key }) => (
                <div key={key} className="px-1 text-center">
                    <Icon
                        className="mx-auto h-6 w-6 text-souk-brown"
                        strokeWidth={1.4}
                    />
                    <p className="mt-2.5 text-[13px] font-medium text-souk-title">
                        {t(`buyer.detail.trust_${key}_title`)}
                    </p>
                    <p className="mt-1 text-[11px] leading-relaxed text-souk-brown-deep/65">
                        {t(`buyer.detail.trust_${key}_body`)}
                    </p>
                </div>
            ))}
        </div>
    );
}

/** A row of product cards, shared by "more from shop" and "you may also like". */
function ProductRow({
    title,
    products,
    money,
}: {
    title: string;
    products: DetailProduct[];
    money: (v: number | string) => string;
}) {
    if (!products?.length) {
        return null;
    }

    return (
        <section className="container border-t border-souk-brown/10 py-14">
            <h2 className="font-display text-2xl text-souk-title">{title}</h2>
            <div className="mt-8 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-4">
                {products.map((p) => {
                    const pSale =
                        p.sale_price &&
                        Number(p.sale_price) < Number(p.base_price);
                    const pTrade = p.professional_effective_price ?? null;

                    return (
                        <Link
                            key={p.id}
                            href={route('products.show', p.slug)}
                            className="group"
                        >
                            <div className="aspect-[4/5] overflow-hidden rounded-t-[3rem] rounded-b-2xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                                {p.images?.[0] ? (
                                    <img
                                        src={p.images[0].url}
                                        alt={p.name}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-souk-brown/25">
                                        <ZellijStarIcon className="h-8 w-8" />
                                    </div>
                                )}
                            </div>
                            <h3 className="mt-3 font-display text-base leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                                {p.name}
                            </h3>
                            <p className="mt-1 text-sm">
                                {pTrade !== null ? (
                                    <span className="text-olive">
                                        {money(pTrade)} MAD
                                    </span>
                                ) : pSale ? (
                                    <>
                                        <span className="text-terracotta">
                                            {money(Number(p.sale_price))} MAD
                                        </span>{' '}
                                        <span className="text-xs text-souk-brown-deep/45 line-through">
                                            {money(Number(p.base_price))}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-souk-title">
                                        {money(Number(p.base_price))} MAD
                                    </span>
                                )}
                            </p>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

const ProductDetail = ({
    product,
    inWishlist,
    inCart,
    moreFromStore,
    relatedProducts,
}: {
    product: DetailProduct;
    inWishlist: boolean;
    inCart: boolean;
    moreFromStore: DetailProduct[];
    relatedProducts: DetailProduct[];
}) => {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props as unknown as {
        auth: {
            user: {
                role?: string;
                professional_verified_at?: string | null;
            } | null;
        };
    };

    const variants = product.variants ?? [];
    const groups = product.option_groups ?? [];
    const hasVariants = groups.length > 0 && variants.length > 0;

    // Start on the first variant that can actually be bought, so the page
    // opens on a valid, in-stock combination rather than a dead end.
    const [selection, setSelection] = useState<Selection>(() => {
        if (!hasVariants) {
            return {};
        }

        const opening = variants.find((v) => v.in_stock) ?? variants[0];

        return Object.fromEntries(
            opening.options.map((o) => [o.name, o.value]),
        );
    });

    const selectedVariant = hasVariants
        ? resolveVariant(variants, selection)
        : null;

    const [activeIdx, setActiveIdx] = useState(0);
    const [qty, setQty] = useState(1);
    const [wished, setWished] = useState(inWishlist);

    const guestCart = useGuestCart();

    /*
     * Variant photography replaces the product's gallery while that variant is
     * selected — that is the point of uploading it. Falls back to the product's
     * own media when the seller gave this variant none, so a partly-illustrated
     * catalogue never shows an empty frame.
     */
    const productMedia: Media[] = [...product.images, ...product.videos];
    const variantMedia = (selectedVariant?.images ?? []).map((img) => ({
        id: img.id,
        url: img.url,
        type: 'image',
    })) as Media[];
    const media = variantMedia.length > 0 ? variantMedia : productMedia;
    // The index is per-gallery; clamp so switching to a variant with fewer
    // photos can't leave it pointing past the end (which renders nothing).
    const active = media[Math.min(activeIdx, media.length - 1)];

    // Stock and availability follow the selection: a variant product's own
    // `stock` column is always 0, so reading it here would call everything
    // sold out.
    const stock = hasVariants
        ? (selectedVariant?.stock ?? 0)
        : Number(product.stock);
    const soldOut = hasVariants
        ? selectedVariant !== null && !selectedVariant.in_stock
        : Number(product.stock) === 0;
    const needsChoice = hasVariants && selectedVariant === null;

    const money = (v: number | string) =>
        new Intl.NumberFormat(LOCALE_TAGS[locale as string] ?? 'fr-MA', {
            maximumFractionDigits: 0,
        }).format(Number(v));

    const onSale =
        product.sale_price &&
        Number(product.sale_price) < Number(product.base_price);

    // A trade account that hasn't been verified yet still pays retail, so it
    // gets a nudge rather than the wholesale figure.
    const isPendingTrade =
        auth.user?.role === 'professional_buyer' &&
        !auth.user?.professional_verified_at;
    const isGuestOrConsumer =
        !auth.user || auth.user.role === 'buyer' || !auth.user.role;

    /** Is `value` still reachable given what's chosen in the other groups? */
    const isReachable = (groupName: string, value: string) =>
        variants.some((v) => {
            if (v.options.find((o) => o.name === groupName)?.value !== value) {
                return false;
            }

            if (!v.in_stock) {
                return false;
            }

            const others = Object.fromEntries(
                Object.entries(selection).filter(([n]) => n !== groupName),
            );

            return matches(v, others);
        });

    const chooseValue = (groupName: string, value: string) => {
        setSelection((prev) => {
            const next = { ...prev, [groupName]: value };

            /*
             * Picking a value that doesn't combine with the current choices
             * would strand the shopper on a non-existent combination. Drop
             * back to just this choice and let them rebuild from there,
             * rather than silently keeping an impossible pair.
             */
            if (!variants.some((v) => matches(v, next))) {
                return { [groupName]: value };
            }

            return next;
        });

        // Show that variant's photography from its first frame.
        setActiveIdx(0);
        setQty(1);
    };

    const handleAddToCart = () => {
        if (needsChoice) {
            return;
        }

        const unitPrice =
            selectedVariant?.price ?? product.sale_price ?? product.base_price;

        if (auth.user) {
            router.post(
                route('cart.add'),
                {
                    product_id: product.id,
                    variant_id: selectedVariant?.id ?? null,
                    quantity: qty,
                },
                { preserveScroll: true },
            );
        } else {
            guestCart.addItem({
                product_id: product.id,
                variant_id: selectedVariant?.id ?? null,
                variant_label: selectedVariant?.label ?? null,
                quantity: qty,
                name: product.name,
                price: String(unitPrice),
                image: media[0]?.url ?? null,
                store_name: product.store.store_name,
            });
        }
    };

    const toggleWishlist = () => {
        // The route is auth-only, so a guest would just get bounced; send them
        // to log in instead of firing a request that cannot succeed.
        if (!auth.user) {
            router.visit(route('login'));

            return;
        }

        // Optimistic, rolled back if the request fails.
        setWished((w) => !w);
        router.post(
            route('products.wishlist', product.id),
            {},
            {
                preserveScroll: true,
                onError: () => setWished((w) => !w),
            },
        );
    };

    const maxQty = Math.max(1, stock || 1);

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            <section className="container grid grid-cols-12 gap-8 pt-6 pb-12 lg:gap-14">
                {/*
                 * Gallery. Sticky so the piece stays in view while the buying
                 * details scroll past it, and height-capped (rather than
                 * filling its column) so a tall photo can't push the page
                 * out of proportion.
                 */}
                <div className="col-span-12 lg:sticky lg:top-6 lg:col-span-6 lg:self-start">
                    <div className="flex gap-3 md:gap-4">
                        {media.length > 1 && (
                            <div className="flex w-16 shrink-0 flex-col gap-3 md:w-[4.5rem]">
                                {media.map((m, i) => (
                                    <button
                                        key={m.id}
                                        onClick={() => setActiveIdx(i)}
                                        aria-label={t(
                                            'buyer.detail.view_media',
                                        ).replace(':n', String(i + 1))}
                                        aria-current={i === activeIdx}
                                        className={`relative aspect-square overflow-hidden rounded-xl ring-1 transition-all ${
                                            i === activeIdx
                                                ? 'ring-2 ring-souk-brown'
                                                : 'ring-souk-brown/15 hover:ring-souk-brown/40'
                                        }`}
                                    >
                                        {m.type === 'video' ? (
                                            <>
                                                <video
                                                    src={m.url}
                                                    className="h-full w-full object-cover"
                                                    muted
                                                />
                                                <span className="absolute inset-0 flex items-center justify-center bg-souk-title/40">
                                                    <Play className="h-3.5 w-3.5 fill-white text-white" />
                                                </span>
                                            </>
                                        ) : (
                                            <img
                                                src={m.url}
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}

                        <div className="relative min-w-0 flex-1 overflow-hidden rounded-2xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                            <div className="aspect-[4/5] max-h-[68vh]">
                                {active?.type === 'video' ? (
                                    <video
                                        key={active.id}
                                        src={active.url}
                                        controls
                                        className="h-full w-full object-cover"
                                    />
                                ) : active ? (
                                    <img
                                        key={active.id}
                                        src={active.url}
                                        alt={product.name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-souk-brown/25">
                                        <ZellijStarIcon className="h-10 w-10" />
                                    </div>
                                )}
                            </div>

                            {onSale && (
                                <span
                                    className={`absolute start-5 top-5 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                                >
                                    {t('buyer.products.badge_sale')}
                                </span>
                            )}
                            {soldOut && (
                                <span
                                    className={`absolute end-5 top-5 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                                >
                                    {t('buyer.detail.sold_out')}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Buying column ──────────────────────────── */}
                <div className="col-span-12 lg:col-span-6">
                    {/* Breadcrumb */}
                    <nav
                        className={`flex flex-wrap items-center gap-x-2 gap-y-1 text-souk-brown-deep/55 ${EYEBROW}`}
                    >
                        <Link
                            href="/"
                            aria-label={t('buyer.detail.home')}
                            className="transition-colors hover:text-souk-title"
                        >
                            <Home className="h-3.5 w-3.5" strokeWidth={1.6} />
                        </Link>
                        <span aria-hidden>/</span>
                        <Link
                            href={route('products.index')}
                            className="transition-colors hover:text-souk-title"
                        >
                            {t('buyer.detail.back_to_market')}
                        </Link>
                        {product.category?.translated_name && (
                            <>
                                <span aria-hidden>/</span>
                                <span>{product.category.translated_name}</span>
                            </>
                        )}
                    </nav>

                    <p className={`mt-7 text-souk-brown ${EYEBROW}`}>
                        {t('buyer.detail.eyebrow_handmade')}
                    </p>
                    <h1 className="mt-3 font-display text-[clamp(1.9rem,3.4vw,2.9rem)] leading-[1.08] tracking-[-0.02em] text-souk-title">
                        {product.name}
                    </h1>

                    {product.description && (
                        <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-souk-brown-deep/80">
                            {product.description}
                        </p>
                    )}

                    {/* Price, with availability opposite it. */}
                    <div className="mt-7 flex flex-wrap items-center justify-between gap-4">
                        <PriceBlock
                            product={product}
                            variant={selectedVariant}
                            money={money}
                            t={t}
                        />

                        <p className="flex items-center gap-1.5 text-sm">
                            {needsChoice ? (
                                <span className="text-souk-brown-deep/60">
                                    {t('buyer.detail.select_variant_prompt')}
                                </span>
                            ) : soldOut ? (
                                <span className="text-terracotta">
                                    {t('buyer.detail.sold_out')}
                                </span>
                            ) : stock <= 5 ? (
                                <span className="text-terracotta">
                                    {t('buyer.detail.only_left').replace(
                                        ':count',
                                        String(stock),
                                    )}
                                </span>
                            ) : (
                                <>
                                    <CheckCircle2
                                        className="h-4 w-4 text-olive"
                                        strokeWidth={1.6}
                                    />
                                    <span className="text-olive">
                                        {t('buyer.detail.in_stock')}
                                    </span>
                                </>
                            )}
                        </p>
                    </div>

                    {isPendingTrade && (
                        <div className="mt-4 flex items-start gap-3 rounded-2xl border border-amber-300/50 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-souk-brown-deep/85">
                            <Clock
                                className="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                                strokeWidth={1.5}
                            />
                            <span>
                                <span className="font-medium text-souk-title">
                                    {t('buyer.detail.trade_pending_title')}
                                </span>{' '}
                                {t('buyer.detail.trade_pending_body')}
                            </span>
                        </div>
                    )}

                    <div className="mt-8 h-px bg-souk-brown/12" />

                    {/* ── Variant selectors, one row per option ── */}
                    {hasVariants &&
                        groups.map((group) => (
                            <div key={group.name} className="mt-6">
                                <p
                                    className={`text-souk-brown-deep/55 ${EYEBROW}`}
                                >
                                    {group.name}
                                    {selection[group.name] && (
                                        <span className="ms-2 tracking-normal text-souk-title normal-case">
                                            {selection[group.name]}
                                        </span>
                                    )}
                                </p>
                                <div className="mt-3 flex flex-wrap items-center gap-2.5">
                                    {group.values.map((v) => (
                                        <OptionValue
                                            key={v.value}
                                            label={v.value}
                                            selected={
                                                selection[group.name] ===
                                                v.value
                                            }
                                            reachable={isReachable(
                                                group.name,
                                                v.value,
                                            )}
                                            onSelect={() =>
                                                chooseValue(group.name, v.value)
                                            }
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}

                    {/* Quantity */}
                    <div className="mt-7">
                        <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                            {t('buyer.detail.quantity')}
                        </p>
                        <div className="mt-3 inline-flex items-center rounded-xl border border-souk-brown/20">
                            <button
                                onClick={() =>
                                    setQty((q) => Math.max(1, q - 1))
                                }
                                disabled={qty <= 1}
                                aria-label={t('buyer.detail.decrease')}
                                className="rounded-s-xl px-4 py-3 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                            >
                                <Minus className="h-3.5 w-3.5" />
                            </button>
                            <span className="min-w-10 px-1 text-center text-sm text-souk-title tabular-nums">
                                {qty}
                            </span>
                            <button
                                onClick={() =>
                                    setQty((q) => Math.min(maxQty, q + 1))
                                }
                                disabled={qty >= maxQty || soldOut}
                                aria-label={t('buyer.detail.increase')}
                                className="rounded-e-xl px-4 py-3 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                            >
                                <Plus className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                        <button
                            onClick={handleAddToCart}
                            disabled={soldOut || needsChoice}
                            className="inline-flex flex-1 items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-4 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-45"
                        >
                            {soldOut
                                ? t('buyer.detail.sold_out')
                                : t('buyer.detail.add_to_cart')}
                            <ShoppingBag
                                className="h-4 w-4"
                                strokeWidth={1.6}
                            />
                        </button>
                        <button
                            onClick={toggleWishlist}
                            aria-pressed={wished}
                            className="inline-flex flex-1 items-center justify-center gap-2.5 rounded-xl border border-souk-brown/25 px-6 py-4 text-sm font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown"
                        >
                            <Heart
                                className={`h-4 w-4 ${wished ? 'fill-terracotta text-terracotta' : ''}`}
                                strokeWidth={1.6}
                            />
                            {wished
                                ? t('buyer.detail.wishlist_remove')
                                : t('buyer.detail.wishlist_add')}
                        </button>
                    </div>

                    {inCart && (
                        <Link
                            href={route('cart.show')}
                            className="mt-3 inline-block text-sm text-souk-brown underline underline-offset-4"
                        >
                            {t('buyer.detail.go_to_cart')}
                        </Link>
                    )}

                    <TrustRow t={t} />

                    {/* Handmade-variation note, as in the reference. */}
                    <div className="mt-7 flex items-start gap-3 rounded-2xl bg-souk-peach px-5 py-4">
                        <Leaf
                            className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                            strokeWidth={1.5}
                        />
                        <p className="text-[13px] leading-relaxed text-souk-brown-deep/85">
                            {t('buyer.detail.handmade_note')}
                        </p>
                    </div>

                    {/* ── Artisan card ── */}
                    <div className="mt-7 rounded-2xl border border-souk-brown/12 p-5">
                        <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                            {t('buyer.detail.sold_by')}
                        </p>
                        <div className="mt-3 flex items-center gap-4">
                            <div className="h-14 w-14 shrink-0 overflow-hidden rounded-full bg-souk-brown/10 ring-1 ring-souk-brown/15">
                                {product.store.logo ? (
                                    <img
                                        src={product.store.logo}
                                        alt={product.store.store_name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-souk-brown">
                                        <StoreIcon
                                            className="h-5 w-5"
                                            strokeWidth={1.5}
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-display text-lg text-souk-title">
                                    {product.store.store_name}
                                </p>
                                {product.store.city_name && (
                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-souk-brown-deep/65">
                                        <MapPin
                                            className="h-3 w-3"
                                            strokeWidth={1.6}
                                        />
                                        {product.store.city_name}
                                    </p>
                                )}
                            </div>
                            {/*
                             * No "visit shop" button: there is no public
                             * storefront route yet. The previous page linked
                             * to /seller/{slug}, which sits behind the
                             * `role:seller` middleware and 403s for the
                             * shoppers this page is built for. Better no
                             * button than one that errors — restore it here
                             * once a public store page exists.
                             */}
                        </div>
                        {product.store.description && (
                            <p className="mt-4 text-[13px] leading-relaxed text-souk-brown-deep/70">
                                {product.store.description}
                            </p>
                        )}
                    </div>

                    <div className="mt-6 space-y-2.5 text-[13px] text-souk-brown-deep/70">
                        <p className="flex items-center gap-2.5">
                            <Truck
                                className="h-4 w-4 shrink-0 text-souk-brown"
                                strokeWidth={1.5}
                            />
                            {t('buyer.detail.delivery')}
                        </p>
                        <p className="flex items-center gap-2.5">
                            <ShieldCheck
                                className="h-4 w-4 shrink-0 text-souk-brown"
                                strokeWidth={1.5}
                            />
                            {t('buyer.detail.authentic')}
                        </p>
                    </div>

                    {/* Trade CTA for consumers and guests. */}
                    {isGuestOrConsumer && (
                        <div className="mt-7 rounded-2xl border border-olive/25 bg-olive/5 p-5">
                            <p className="font-display text-lg text-souk-title">
                                {t('buyer.detail.trade_cta_title')}
                            </p>
                            <p className="mt-1.5 text-[13px] leading-relaxed text-souk-brown-deep/75">
                                {t('buyer.detail.trade_cta_body')}
                            </p>
                            <Link
                                href={route('register')}
                                className="mt-4 inline-block rounded-lg bg-olive px-4 py-2.5 text-xs font-medium text-white transition-opacity hover:opacity-90"
                            >
                                {t('buyer.detail.trade_cta_button')}
                            </Link>
                        </div>
                    )}
                </div>
            </section>

            <ProductRow
                title={t('buyer.detail.more_from_store')}
                products={moreFromStore}
                money={money}
            />
            <ProductRow
                title={t('buyer.detail.related')}
                products={relatedProducts}
                money={money}
            />

            <SiteFooter />
        </div>
    );
};

export default ProductDetail;
