import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgePercent,
    Heart,
    Clock,
    MapPin,
    Minus,
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
import type { BuyerProductResource } from '@/types/extended_models';

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

/**
 * Price block.
 *
 * A verified trade buyer sees their wholesale rate as *the* price, with the
 * public price struck through — CheckoutService bills exactly this figure, so
 * it can be stated plainly rather than hedged as "indicative". Everyone else
 * sees retail. The two come from the same `Product::audienceFor()` decision
 * server-side, so this can't quote a number the basket won't honour.
 */
function PriceBlock({
    product,
    money,
    t,
}: {
    product: DetailProduct;
    money: (v: number | string) => string;
    t: (k: string) => string;
}) {
    const base = Number(product.base_price);
    const sale = product.sale_price ? Number(product.sale_price) : null;
    const onSale = sale !== null && sale < base;
    const retail = onSale ? sale : base;

    const trade = product.professional_effective_price ?? null;
    const hasTrade = trade !== null && trade < retail;
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
        <div>
            <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                {t('buyer.detail.public_price')}
            </p>
            <div className="mt-2 flex flex-wrap items-baseline gap-x-3">
                <span
                    className={`font-display text-4xl leading-none ${onSale ? 'text-terracotta' : 'text-souk-title'}`}
                >
                    {money(retail)}
                    <span className="ms-1.5 text-base">MAD</span>
                </span>
                {onSale && (
                    <span className="text-sm text-souk-brown-deep/50 line-through">
                        {money(base)} MAD
                    </span>
                )}
            </div>
        </div>
    );
}

const ProductDetail = ({
    product,
    inWishlist,
    inCart,
    moreFromStore,
}: {
    product: DetailProduct;
    inWishlist: boolean;
    inCart: boolean;
    moreFromStore: DetailProduct[];
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

    const media: Media[] = [...product.images, ...product.videos];
    const [activeIdx, setActiveIdx] = useState(0);
    const [qty, setQty] = useState(1);
    const [wished, setWished] = useState(inWishlist);

    const guestCart = useGuestCart();
    const active = media[activeIdx];
    const soldOut = product.stock === 0;

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

    const handleAddToCart = () => {
        if (auth.user) {
            router.post(
                route('cart.add'),
                { product_id: product.id, quantity: qty },
                { preserveScroll: true },
            );
        } else {
            guestCart.addItem({
                product_id: product.id,
                quantity: qty,
                name: product.name,
                price: String(product.sale_price ?? product.base_price),
                image: product.images[0]?.url ?? null,
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

        // Optimistic, rolled back if the request fails. Previously this button
        // only flipped local state and never told the server at all, so a
        // "favourited" piece was silently forgotten on reload.
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

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/* ── Breadcrumb ─────────────────────────────────── */}
            <div
                className={`container pt-8 pb-5 text-souk-brown-deep/55 ${EYEBROW}`}
            >
                <Link
                    href={route('products.index')}
                    className="transition-colors hover:text-souk-title"
                >
                    {t('buyer.detail.back_to_market')}
                </Link>
                {product.category?.translated_name && (
                    <> · {product.category.translated_name}</>
                )}
            </div>

            <section className="container grid grid-cols-12 gap-8 pb-16 md:gap-12">
                {/* ── Gallery: rail of thumbnails beside the main image ── */}
                <div className="col-span-12 md:col-span-7">
                    <div className="flex gap-3 md:gap-4">
                        {media.length > 1 && (
                            <div className="flex w-16 shrink-0 flex-col gap-3 md:w-20">
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

                        {/* Arch-topped main frame, echoing the listing cards. */}
                        <div className="relative min-w-0 flex-1 overflow-hidden rounded-t-[6rem] rounded-b-3xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                            <div className="aspect-[4/5]">
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
                                    className={`absolute start-6 top-6 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                                >
                                    {t('buyer.products.badge_sale')}
                                </span>
                            )}
                            {soldOut && (
                                <span
                                    className={`absolute end-6 top-6 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                                >
                                    {t('buyer.detail.sold_out')}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Buying column ──────────────────────────── */}
                <div className="col-span-12 md:col-span-5">
                    <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                        {product.category?.translated_name}
                    </p>
                    <h1 className="mt-3 font-display text-[clamp(1.9rem,3.4vw,2.75rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                        {product.name}
                    </h1>

                    <div className="mt-6">
                        <PriceBlock product={product} money={money} t={t} />
                    </div>

                    {/* Trade nudges — one for consumers, one for a trade
                        account still awaiting verification. */}
                    {isPendingTrade && (
                        <div className="mt-4 flex items-start gap-3 rounded-2xl border border-amber-300/50 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-souk-brown-deep/85">
                            <Clock
                                className="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                                strokeWidth={1.5}
                            />
                            <span>
                                <span className="font-medium text-souk-title">
                                    {t(
                                        'buyer.detail.trade_pending_title',
                                    )}
                                </span>{' '}
                                {t('buyer.detail.trade_pending_body')}
                            </span>
                        </div>
                    )}

                    <p className={`mt-5 ${EYEBROW}`}>
                        {soldOut ? (
                            <span className="text-terracotta">
                                {t('buyer.detail.sold_out')}
                            </span>
                        ) : product.stock <= 5 ? (
                            <span className="text-terracotta">
                                {t('buyer.detail.only_left').replace(
                                    ':count',
                                    String(product.stock),
                                )}
                            </span>
                        ) : (
                            <span className="text-olive">
                                {t('buyer.detail.in_stock')}
                            </span>
                        )}
                    </p>

                    {/* Quantity + actions */}
                    <div className="mt-7 space-y-4">
                        <div className="flex items-center gap-4">
                            <span
                                className={`text-souk-brown-deep/55 ${EYEBROW}`}
                            >
                                {t('buyer.detail.quantity')}
                            </span>
                            <div className="inline-flex items-center rounded-full border border-souk-brown/20">
                                <button
                                    onClick={() =>
                                        setQty((q) => Math.max(1, q - 1))
                                    }
                                    disabled={qty <= 1}
                                    aria-label={t(
                                        'buyer.detail.decrease',
                                    )}
                                    className="rounded-full px-3.5 py-2 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                                >
                                    <Minus className="h-3.5 w-3.5" />
                                </button>
                                <span className="min-w-8 px-1 text-center text-sm text-souk-title tabular-nums">
                                    {qty}
                                </span>
                                <button
                                    onClick={() =>
                                        setQty((q) =>
                                            Math.min(
                                                product.stock || 99,
                                                q + 1,
                                            ),
                                        )
                                    }
                                    disabled={qty >= product.stock}
                                    aria-label={t(
                                        'buyer.detail.increase',
                                    )}
                                    className="rounded-full px-3.5 py-2 text-souk-brown-deep transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-souk-brown-deep"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            <button
                                type="button"
                                disabled={soldOut}
                                onClick={
                                    inCart
                                        ? () => router.visit(route('cart.show'))
                                        : handleAddToCart
                                }
                                className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-souk-brown text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <ShoppingBag className="h-4 w-4" />
                                {inCart
                                    ? t('buyer.detail.go_to_cart')
                                    : t('buyer.detail.add_to_cart')}
                            </button>
                            <button
                                type="button"
                                onClick={toggleWishlist}
                                aria-pressed={wished}
                                aria-label={t(
                                    wished
                                        ? 'buyer.detail.wishlist_remove'
                                        : 'buyer.detail.wishlist_add',
                                )}
                                className={`inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full border transition-colors ${
                                    wished
                                        ? 'border-terracotta bg-terracotta text-white'
                                        : 'border-souk-brown/25 text-souk-brown-deep hover:border-souk-brown hover:text-souk-title'
                                }`}
                            >
                                <Heart
                                    className={`h-4 w-4 ${wished ? 'fill-white' : ''}`}
                                />
                            </button>
                        </div>
                    </div>

                    {/* ── Seller card ─────────────────────────── */}
                    <div className="mt-8 rounded-2xl border border-souk-brown/12 bg-souk-peach/40 p-5">
                        <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                            {t('buyer.detail.sold_by')}
                        </p>
                        <div className="mt-3 flex items-center gap-4">
                            {product.store.logo ? (
                                <img
                                    src={product.store.logo}
                                    alt={product.store.store_name ?? ''}
                                    className="h-14 w-14 shrink-0 rounded-full object-cover ring-1 ring-souk-brown/15"
                                />
                            ) : (
                                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-souk-brown/10 ring-1 ring-souk-brown/15">
                                    <StoreIcon
                                        className="h-6 w-6 text-souk-brown/60"
                                        strokeWidth={1.5}
                                    />
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-display text-lg text-souk-title">
                                    {product.store.store_name}
                                </p>
                                {product.store.city_name && (
                                    <p className="mt-0.5 flex items-center gap-1.5 text-sm text-souk-brown-deep/70">
                                        <MapPin
                                            className="h-3.5 w-3.5"
                                            strokeWidth={1.5}
                                        />
                                        {product.store.city_name}
                                    </p>
                                )}
                            </div>
                        </div>
                        {product.store.description && (
                            <p className="mt-4 line-clamp-3 text-sm leading-relaxed text-souk-brown-deep/75">
                                {product.store.description}
                            </p>
                        )}
                        <Link
                            href={`/seller/${product.store.slug}`}
                            className="mt-4 inline-flex rounded-full border border-souk-brown/25 px-4 py-2 text-sm text-souk-brown-deep transition-colors hover:border-souk-brown hover:bg-souk-brown hover:text-souk-cream"
                        >
                            {t('buyer.detail.visit_store')}
                        </Link>
                    </div>

                    {/* Trust strip */}
                    <div className="mt-7 grid gap-3 border-t border-souk-brown/10 pt-6 text-xs text-souk-brown-deep/75 sm:grid-cols-2">
                        <p className="flex items-start gap-2">
                            <Truck
                                className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                                strokeWidth={1.5}
                            />
                            {t('buyer.detail.delivery')}
                        </p>
                        <p className="flex items-start gap-2">
                            <ShieldCheck
                                className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                                strokeWidth={1.5}
                            />
                            {t('buyer.detail.authentic')}
                        </p>
                    </div>
                </div>
            </section>

            {/* ── Description ────────────────────────────────── */}
            <section className="container pb-16">
                <div className="max-w-3xl">
                    <div className="flex items-center gap-3">
                        <ZellijStarIcon className="h-3.5 w-3.5 shrink-0 text-terracotta/70" />
                        <h2 className={`text-souk-brown-deep/60 ${EYEBROW}`}>
                            {t('buyer.detail.description')}
                        </h2>
                        <span className="h-px flex-1 bg-souk-brown/15" />
                    </div>
                    <p className="mt-5 leading-relaxed whitespace-pre-line text-souk-brown-deep/85">
                        {product.description ||
                            t('buyer.detail.no_description')}
                    </p>
                </div>
            </section>

            {/* ── Trade CTA for consumers ─────────────────────── */}
            {isGuestOrConsumer && (
                <section className="container pb-16">
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-souk-brown/12 bg-souk-peach/50 px-6 py-5">
                        <div className="max-w-xl">
                            <p className="font-display text-lg text-souk-title">
                                {t('buyer.detail.trade_cta_title')}
                            </p>
                            <p className="mt-1 text-sm text-souk-brown-deep/80">
                                {t('buyer.detail.trade_cta_body')}
                            </p>
                        </div>
                        <Link
                            href={route('register')}
                            className="shrink-0 rounded-full bg-souk-brown px-5 py-2.5 text-sm text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('buyer.detail.trade_cta_button')}
                        </Link>
                    </div>
                </section>
            )}

            {/* ── More from the same workshop ─────────────────── */}
            {moreFromStore.length > 0 && (
                <section className="container pb-24">
                    <div className="mb-8 flex items-center gap-3">
                        <ZellijStarIcon className="h-3.5 w-3.5 shrink-0 text-terracotta/70" />
                        <h2 className={`text-souk-brown-deep/60 ${EYEBROW}`}>
                            {t('buyer.detail.more_from_store')}
                        </h2>
                        <span className="h-px flex-1 bg-souk-brown/15" />
                    </div>

                    <div className="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-4">
                        {moreFromStore.map((p) => {
                            const cover = p.images[0]?.url;
                            const pSale =
                                p.sale_price &&
                                Number(p.sale_price) < Number(p.base_price);
                            const pTrade =
                                p.professional_effective_price ?? null;

                            return (
                                <Link
                                    key={p.id}
                                    href={`/products/${p.slug}`}
                                    className="group block"
                                >
                                    <div className="aspect-[4/5] overflow-hidden rounded-t-[4rem] rounded-b-2xl bg-souk-brown/5 ring-1 ring-souk-brown/10 transition-all duration-500 group-hover:-translate-y-1 group-hover:shadow-xl group-hover:shadow-souk-brown/15">
                                        {cover && (
                                            <img
                                                src={cover}
                                                alt={p.name}
                                                loading="lazy"
                                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05]"
                                            />
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
                                                    {money(
                                                        Number(p.sale_price),
                                                    )}{' '}
                                                    MAD
                                                </span>{' '}
                                                <span className="text-xs text-souk-brown-deep/45 line-through">
                                                    {money(
                                                        Number(p.base_price),
                                                    )}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-souk-title">
                                                {money(Number(p.base_price))}{' '}
                                                MAD
                                            </span>
                                        )}
                                    </p>
                                </Link>
                            );
                        })}
                    </div>
                </section>
            )}

            <SiteFooter />
        </div>
    );
};

export default ProductDetail;
