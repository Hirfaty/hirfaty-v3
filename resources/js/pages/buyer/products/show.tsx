import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgePercent,
    Check,
    CheckCircle2,
    Clock,
    Hand,
    Heart,
    Home,
    Leaf,
    LogIn,
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
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import VerifiedBadge from '@/components/buyer/VerifiedBadge';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useCartSheet } from '@/contexts/CartSheetContext';
import { useCurrency } from '@/hooks/use-currency';
import { useHoverGallery } from '@/hooks/use-hover-gallery';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import type { Media } from '@/types';
import type {
    BuyerProductResource,
    ProductVariantResource,
} from '@/types/extended_models';

/** Museum-caption label, matching the listing page. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';


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
    /*
     * Demo-only state: an admin can catalogue a piece before its price is
     * agreed, temporarily reachable here via `storefront(false)` — see
     * `Product::scopeStorefront()`. A selected variant with no override
     * inherits this same null from the product, which is why the check reads
     * the variant's own resolved price rather than the product's directly.
     */
    const unpriced =
        variant !== null ? variant.price == null : product.base_price === null;

    if (unpriced) {
        return (
            <span className="text-lg text-souk-brown-deep/45 italic">
                {t('buyer.products.price_coming_soon')}
            </span>
        );
    }

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

    if (hasTrade) {
        return (
            <div className="rounded-2xl border border-olive/25 bg-olive/5 p-5">
                <p className={`text-olive/80 ${EYEBROW}`}>
                    {t('buyer.detail.your_trade_price')}
                </p>
                <div className="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span className="font-display text-4xl leading-none text-olive">
                        {money(trade)}
                        <span className="ms-1.5 text-base">TTC</span>
                    </span>
                    <span className="text-sm text-souk-brown-deep/50 line-through">
                        {money(retail)} TTC
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
                    {/*
                     * The bulk minimum used to be repeated here. It now sits
                     * on the quantity stepper instead — the one place where
                     * the buyer can actually run into it.
                     */}
                    {t('buyer.detail.trade_billed_note')}
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
                <span className="ms-1.5 text-lg">TTC</span>
            </span>
            {strike && (
                <span className="text-base text-souk-brown-deep/45 line-through">
                    {money(strike)} TTC
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

/**
 * One tile in a "more from shop" / "you may also like" row.
 *
 * Split out from `ProductRow`'s `.map()` rather than left inline:
 * `useHoverGallery` is a hook, and hooks may only run at the top level of a
 * component — calling one from inside a loop callback would run it once per
 * item rather than tying it to one, tripping the Rules of Hooks. Same reason
 * `ProductShowcase`'s `ShowcaseCard` and `CollectionCard` are their own
 * components.
 */
function RelatedProductCard({
    product: p,
    money,
    t,
}: {
    product: DetailProduct;
    money: (v: number | string) => string;
    t: (k: string) => string;
}) {
    const gallery = p.gallery_images ?? p.images;
    const { index, previous, hoverProps } = useHoverGallery(gallery.length);

    // Demo-only state: see DetailProduct's base_price note further down.
    // Nothing else in this card means anything without a retail figure to
    // compare against.
    const pUnpriced = p.base_price === null;
    const pSale =
        !pUnpriced &&
        p.sale_price &&
        Number(p.sale_price) < Number(p.base_price);
    const pTrade = pUnpriced ? null : (p.professional_effective_price ?? null);

    return (
        <Link
            {...hoverProps}
            href={route('products.show', p.slug)}
            className="group block"
        >
            <div className="relative aspect-[4/5] overflow-hidden rounded-xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                {gallery.length > 0 ? (
                    // Same dip-free crossfade stack as `CollectionCard` and
                    // `ProductShowcase` — see either for why the outgoing frame
                    // stays opaque underneath the incoming one rather than
                    // the two simply swapping opacity together.
                    <div className="absolute inset-0 isolate">
                        {gallery.map((image, i) => {
                            const active = i === index;
                            const outgoing = i === previous && i !== index;

                            return (
                                <div
                                    key={image.id}
                                    className={`absolute inset-0 ${
                                        active
                                            ? 'z-20 opacity-100 transition-opacity duration-500 ease-out'
                                            : outgoing
                                              ? 'z-10 opacity-100'
                                              : 'z-0 opacity-0'
                                    }`}
                                >
                                    <img
                                        src={image.url}
                                        alt={i === 0 ? p.name : ''}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                    />
                                </div>
                            );
                        })}
                    </div>
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
                {pUnpriced ? (
                    <span className="text-souk-brown-deep/45 italic">
                        {t('buyer.products.price_coming_soon')}
                    </span>
                ) : pTrade !== null ? (
                    <span className="text-olive">{money(pTrade)} TTC</span>
                ) : pSale ? (
                    <>
                        <span className="text-terracotta">
                            {money(Number(p.sale_price))} TTC
                        </span>{' '}
                        <span className="text-xs text-souk-brown-deep/45 line-through">
                            {money(Number(p.base_price))}
                        </span>
                    </>
                ) : (
                    <span className="text-souk-title">
                        {money(Number(p.base_price))} TTC
                    </span>
                )}
            </p>
        </Link>
    );
}

/** A row of product cards, shared by "more from shop" and "you may also like". */
function ProductRow({
    title,
    products,
    money,
    t,
}: {
    title: string;
    products: DetailProduct[];
    money: (v: number | string) => string;
    t: (k: string) => string;
}) {
    if (!products?.length) {
        return null;
    }

    return (
        <section className="container border-t border-souk-brown/10 py-14">
            <h2 className="font-display text-2xl text-souk-title">{title}</h2>
            <div className="mt-8 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-4">
                {products.map((p) => (
                    <RelatedProductCard
                        key={p.id}
                        product={p}
                        money={money}
                        t={t}
                    />
                ))}
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
    const { t } = useTranslation();
    /* The drawer SiteHeader mounts — opened once the line is actually in. */
    const { openCart } = useCartSheet();
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

    /**
     * The smallest quantity this viewer is allowed to order.
     *
     * Only ever above 1 for a verified trade account: the resource merges
     * `min_order_qty_professional` in for the professional audience alone, so
     * a consumer reading the same product simply gets 1. Wholesale rates are
     * granted on the understanding that the trade buys in bulk, which is why
     * the floor travels with the price list rather than with the product.
     */
    const minQty = product.min_order_qty_professional ?? 1;

    const [activeIdx, setActiveIdx] = useState(0);
    // Opens on the minimum rather than 1, so a trade buyer isn't shown a
    // quantity their own price list would refuse.
    const [qty, setQty] = useState(minQty);
    const [wished, setWished] = useState(inWishlist);
    /**
     * Holds the Add-to-Cart button in its confirming state for 3s after a
     * click, so the action reads as having landed even though the guest path
     * is instantaneous and the server path only re-renders the header count.
     */
    const [adding, setAdding] = useState(false);

    useEffect(() => {
        if (!adding) {
            return;
        }

        const timer = setTimeout(() => setAdding(false), 3000);

        // Cleared on unmount too, so navigating away mid-window can't fire
        // setState on a gone component.
        return () => clearTimeout(timer);
    }, [adding]);

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
    /*
     * Demo-only state: see `Product::scopeStorefront()`'s docblock — an
     * unpriced product is temporarily reachable here, but nothing downstream
     * (cart, checkout) will bill it, so the buy actions stay closed. Not
     * meaningful while `needsChoice` is true: a variant's own price can
     * differ from the product's, so this only resolves once one is picked.
     */
    const unpriced = hasVariants
        ? selectedVariant !== null && selectedVariant.price == null
        : product.base_price === null;
    /*
     * In stock, but not by enough to clear the trade minimum. Distinct from
     * sold out: the piece is available, just not in the quantity this buyer is
     * obliged to take, and saying "out of stock" would send them away from
     * something a restock would let them buy.
     */
    const belowMinimum = !needsChoice && !soldOut && stock < minQty;

    /*
     * Prices arrive from the server in MAD. `money()` renders them in the
     * currency this visitor is reading (their own pick, or their IP's) and
     * appends the currency itself — so nothing here should add a suffix.
     */
    const { money } = useCurrency();

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
        setQty(minQty);
    };

    /**
     * Add to cart, or send a guest to sign in first.
     *
     * There is no longer a localStorage path here. The basket a guest built
     * was priced entirely client-side, and with two price lists in play that
     * is no longer a safe thing to do: which figure a line is worth depends on
     * whether an admin has verified the account, and whether a product may be
     * bought in ones or only by the twenty. Neither is knowable before we know
     * who is shopping, so the basket starts once they have signed in.
     */
    const handleAddToCart = () => {
        if (needsChoice || unpriced || soldOut || belowMinimum || adding) {
            return;
        }

        if (!auth.user) {
            router.visit(route('login'));

            return;
        }

        setAdding(true);

        router.post(
            route('cart.add'),
            {
                product_id: product.id,
                variant_id: selectedVariant?.id ?? null,
                quantity: qty,
            },
            {
                preserveScroll: true,
                /*
                 * The drawer *is* the confirmation — it shows the line that
                 * just landed, priced, against the rest of the basket. A
                 * success toast on top of it would say the same thing twice.
                 * The response carries the refreshed `cart` shared prop, so
                 * the panel is already current by the time it slides in.
                 */
                onSuccess: () => openCart(),
                /*
                 * The server still refuses on a stock race, a variant pulled
                 * mid-visit, or a quantity under the trade minimum. Surface
                 * its message rather than showing a success toast for
                 * something that didn't happen — and drop the confirming
                 * state, which otherwise sat there reading "added" for three
                 * seconds after a rejection.
                 */
                onError: (errors) => {
                    setAdding(false);
                    toast.error(
                        Object.values(errors)[0] ??
                            t('buyer.detail.add_failed'),
                    );
                },
            },
        );
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

    const maxQty = Math.max(minQty, stock || minQty);

    return (
        <div className="min-h-screen bg-souk-paper">
            <SiteHeader />

            {/*
             * No top padding at lg: the gallery is a full viewport tall and
             * pins at top-0, so a padded row would leave a strip of page
             * showing above it once it sticks.
             */}
            <section className="container mt-8 grid grid-cols-12 gap-8 pt-6 pb-12 lg:gap-14 lg:pt-0">
                {/*
                 * Gallery. Sticky and a full viewport tall, so the piece holds
                 * the screen while the buying details scroll past it. The
                 * thumbnails sit *over* the photograph's top corner rather than
                 * in a rail beside it — a rail would eat width the image needs
                 * to fill that height without letterboxing.
                 *
                 * On small screens it drops to a fixed 70svh: `h-screen` there
                 * would push the price and the Add-to-Cart button entirely
                 * below the fold. svh, not vh, so the mobile browser chrome
                 * collapsing doesn't resize it mid-scroll.
                 */}
                <div className="col-span-12 h-[70svh] lg:sticky lg:top-0 lg:col-span-6 lg:h-screen lg:self-start">
                    <div className="relative h-full w-full overflow-hidden rounded-2xl bg-souk-brown/5 ring-1 ring-souk-brown/10 lg:rounded-none lg:ring-0">
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

                        {/* Thumbnails, over the top-left of the photograph. */}
                        {media.length > 1 && (
                            <div className="absolute start-4 top-4 flex max-h-[calc(100%-2rem)] flex-col gap-2.5 overflow-y-auto md:start-5 md:top-5">
                                {media.map((m, i) => (
                                    <button
                                        key={m.id}
                                        onClick={() => setActiveIdx(i)}
                                        aria-label={t(
                                            'buyer.detail.view_media',
                                        ).replace(':n', String(i + 1))}
                                        aria-current={i === activeIdx}
                                        className={`relative h-14 w-14 shrink-0 overflow-hidden rounded-lg ring-1 backdrop-blur transition-all md:h-16 md:w-16 ${
                                            i === activeIdx
                                                ? 'ring-2 ring-white'
                                                : 'ring-white/45 hover:ring-white/80'
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

                        {onSale && (
                            <span
                                className={`absolute end-5 top-5 rounded-full bg-terracotta px-3 py-1 text-white ${EYEBROW}`}
                            >
                                {t('buyer.products.badge_sale')}
                            </span>
                        )}
                        {soldOut && (
                            <span
                                className={`absolute end-5 bottom-5 rounded-full bg-souk-title/85 px-3 py-1 text-souk-cream backdrop-blur ${EYEBROW}`}
                            >
                                {t('buyer.detail.sold_out')}
                            </span>
                        )}
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

                        {/*
                         * Nothing rendered here while unpriced — PriceBlock
                         * already says so, and a stock note on top of that
                         * would be double-stating a piece that can't be
                         * bought either way yet.
                         */}
                        <p className="flex items-center gap-1.5 text-sm">
                            {needsChoice ? (
                                <span className="text-souk-brown-deep/60">
                                    {t('buyer.detail.select_variant_prompt')}
                                </span>
                            ) : unpriced ? null : soldOut ? (
                                <span className="text-terracotta">
                                    {t('buyer.detail.sold_out')}
                                </span>
                            ) : belowMinimum ? (
                                <span className="text-terracotta">
                                    {t('buyer.detail.below_min_stock')
                                        .replace(':count', String(stock))
                                        .replace(':min', String(minQty))}
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
                            {minQty > 1 && (
                                <span className="ms-2 tracking-normal text-olive normal-case">
                                    {t('buyer.detail.trade_min_qty').replace(
                                        ':count',
                                        String(minQty),
                                    )}
                                </span>
                            )}
                        </p>
                        <div className="mt-3 inline-flex items-center rounded-xl border border-souk-brown/20">
                            <button
                                onClick={() =>
                                    setQty((q) => Math.max(minQty, q - 1))
                                }
                                disabled={qty <= minQty}
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
                            /*
                             * A guest is never disabled — the button is their
                             * way in to signing up, and greying it out would
                             * leave the page with no route forward at all.
                             */
                            disabled={
                                auth.user
                                    ? soldOut ||
                                      needsChoice ||
                                      unpriced ||
                                      belowMinimum ||
                                      adding
                                    : false
                            }
                            aria-live="polite"
                            className={`inline-flex flex-1 items-center justify-center gap-2.5 rounded-xl px-6 py-4 text-sm font-medium transition-colors disabled:cursor-not-allowed ${
                                adding
                                    ? // Not greyed out like the other disabled
                                      // states: this one is a confirmation, so
                                      // it has to stay legible.
                                      'bg-olive text-white disabled:opacity-100'
                                    : 'bg-souk-brown text-souk-cream hover:bg-souk-brown-deep disabled:opacity-45'
                            }`}
                        >
                            {adding ? (
                                <>
                                    {t('buyer.detail.adding')}
                                    <Check
                                        className="h-4 w-4"
                                        strokeWidth={2.2}
                                    />
                                </>
                            ) : !auth.user ? (
                                <>
                                    {t('buyer.detail.login_to_buy')}
                                    <LogIn
                                        className="h-4 w-4"
                                        strokeWidth={1.6}
                                    />
                                </>
                            ) : unpriced ? (
                                <>
                                    {t('buyer.detail.price_coming_soon')}
                                    <Clock
                                        className="h-4 w-4"
                                        strokeWidth={1.6}
                                    />
                                </>
                            ) : (
                                <>
                                    {soldOut
                                        ? t('buyer.detail.sold_out')
                                        : belowMinimum
                                          ? t('buyer.detail.below_min_qty')
                                          : t('buyer.detail.add_to_cart')}
                                    <ShoppingBag
                                        className="h-4 w-4"
                                        strokeWidth={1.6}
                                    />
                                </>
                            )}
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

                    {!auth.user && (
                        <p className="mt-3 text-[13px] leading-relaxed text-souk-brown-deep/70">
                            {t('buyer.detail.login_to_buy_hint')}{' '}
                            <Link
                                href={login()}
                                className="text-souk-brown underline underline-offset-4"
                            >
                                {t('buyer.detail.login_to_buy_register')}
                            </Link>
                        </p>
                    )}

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
                                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 font-display text-xl text-souk-title">
                                    {product.store.store_name}
                                    {product.store.is_verified && (
                                        <VerifiedBadge />
                                    )}
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
                             * Points at the shop's public page, not
                             * /seller/{slug} — that one is the artisan's own
                             * back office behind `role:seller` and 403s for
                             * the shoppers this page is built for, which is
                             * why the button was pulled until now.
                             */}
                            {product.store.slug && (
                                <Link
                                    href={route(
                                        'stores.show',
                                        product.store.slug,
                                    )}
                                    className="shrink-0 rounded-xl border border-souk-brown/25 px-4 py-2 text-xs font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown"
                                >
                                    {t('buyer.detail.visit_store')}
                                </Link>
                            )}
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
                            <p className="font-display text-xl text-souk-title">
                                {t('buyer.detail.trade_cta_title')}
                            </p>
                            <p className="mt-1.5 text-[13px] leading-relaxed text-souk-brown-deep/75">
                                {t('buyer.detail.trade_cta_body')}
                            </p>
                            {/* One target for everyone: the trade page is
                                public now, so a guest reads what they would
                                be signing up for instead of landing on a bare
                                registration form. */}
                            <Link
                                href={route('professional.show')}
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
                t={t}
            />
            <ProductRow
                title={t('buyer.detail.related')}
                products={relatedProducts}
                money={money}
                t={t}
            />

            <SiteFooter />
        </div>
    );
};

export default ProductDetail;
