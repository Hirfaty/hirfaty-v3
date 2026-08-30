import { Link, router, usePage } from '@inertiajs/react';
import {
    Heart,
    ShoppingBag,
    Star,
    MapPin,
    Truck,
    ShieldCheck,
    Minus,
    Plus,
    Play,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useGuestCart } from '@/hooks/useGuestCart';
import type { Media } from '@/types';
import { Category, Product, Store } from '@/types';
import type { BuyerProductResource } from '@/types/extended_models';

type Review = {
    id: number;
    author: string;
    date: string;
    rating: number;
    body: string;
};

const ProductDetail = ({
    product,
    inWishlist,
    inCart,
    moreFromStore,
}: {
    product: BuyerProductResource;
    inWishlist: boolean;
    inCart: boolean;
    moreFromStore: BuyerProductResource[];
}) => {
    console.log({ product, inWishlist, inCart, moreFromStore });
    const { auth } = usePage().props;
    const initialWish = inWishlist;
    const mockReviews: Review[] = [];
    const wishlistsCount =
        (product as BuyerProductResource & { wishlists_count?: number })
            .wishlists_count ?? 0;
    const media = useMemo<Media[]>(
        () => [...product.images, ...product.videos],
        [product],
    );

    const [activeIdx, setActiveIdx] = useState(0);
    const [qty, setQty] = useState(1);
    const [wished, setWished] = useState(inWishlist);

    const active = media[activeIdx];
    const onSale =
        product.sale_price &&
        Number(product.sale_price) < Number(product.base_price);
    const price = onSale
        ? Number(product.sale_price)
        : Number(product.base_price);
    const rating = Number(product.average_rating);
    const guestCart = useGuestCart();

    const handleAddToCart = () => {
        if (auth.user) {
            router.post(
                route('cart.add'),
                {
                    product_id: product.id,
                    quantity: qty,
                },
                {
                    preserveScroll: true,
                },
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
            alert('Added to cart! Login to checkout.');
        }
    };

    const goToCart = () => {
        router.visit('/cart');
    };

    return (
        <div className="min-h-screen bg-cream paper-grain">
            <SiteHeader />

            {/* Breadcrumb */}
            <div className="container pt-8 pb-4 text-xs tracking-[0.18em] text-ink-soft uppercase">
                <Link href="/products" className="hover:text-ink">
                    Marché
                </Link>
                {product.category?.translated_name && (
                    <>
                        {' '}
                        / <span>{product.category?.translated_name}</span>
                    </>
                )}
                {' / '}
                <span className="text-ink">{product.name}</span>
            </div>

            {/* Main */}
            <section className="container grid grid-cols-12 gap-8 pb-16 md:gap-12">
                {/* Gallery */}
                <div className="col-span-12 md:col-span-6">
                    <div className="relative mx-auto aspect-square max-w-md overflow-hidden bg-ink/5 md:mx-0 md:max-w-lg">
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
                        ) : null}

                        {onSale && (
                            <span className="absolute top-4 left-4 bg-terracotta px-2.5 py-1 text-[10px] tracking-[0.2em] text-cream uppercase">
                                Promo
                            </span>
                        )}
                    </div>

                    {media.length > 1 && (
                        <div className="mx-auto mt-3 grid max-w-md grid-cols-5 gap-2 md:mx-0 md:max-w-lg">
                            {media.map((m, i) => (
                                <button
                                    key={m.id}
                                    onClick={() => setActiveIdx(i)}
                                    className={`relative aspect-square overflow-hidden border ${
                                        i === activeIdx
                                            ? 'border-ink'
                                            : 'border-transparent'
                                    }`}
                                >
                                    {m.type === 'video' ? (
                                        <>
                                            <video
                                                src={m.url}
                                                className="h-full w-full object-cover"
                                                muted
                                            />
                                            <span className="absolute inset-0 flex items-center justify-center bg-ink/40">
                                                <Play className="h-4 w-4 fill-cream text-cream" />
                                            </span>
                                        </>
                                    ) : (
                                        <img
                                            src={m.url}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Info */}
                <div className="col-span-12 md:col-span-5">
                    <p className="mb-3 eyebrow">
                        {product.category?.translated_name ?? 'Pièce unique'}
                    </p>
                    <h1 className="h-editorial text-4xl leading-tight text-ink md:text-5xl">
                        {product.name}
                    </h1>

                    {/* Store + rating */}
                    <div className="mt-4 flex items-center gap-4 text-sm text-ink-soft">
                        <Link
                            href={`/seller/${product.store.slug}`}
                            className="link-underline text-ink"
                        >
                            {product.store.store_name}
                        </Link>
                        {product.store.city_name && (
                            <span className="inline-flex items-center gap-1">
                                <MapPin className="h-3.5 w-3.5" />{' '}
                                {product.store.city_name}
                            </span>
                        )}
                        {rating > 0 && (
                            <span className="inline-flex items-center gap-1">
                                <Star
                                    className="h-3.5 w-3.5 fill-terracotta text-terracotta"
                                    strokeWidth={1.5}
                                />
                                {rating.toFixed(1)}
                            </span>
                        )}
                    </div>

                    {/* Price */}
                    <div className="mt-6 flex items-baseline gap-3">
                        <span className="font-display text-3xl text-ink">
                            {price}{' '}
                            <span className="text-base text-ink-soft">MAD</span>
                        </span>
                        {onSale && (
                            <span className="text-sm text-ink-soft line-through">
                                {Number(product.base_price)} MAD
                            </span>
                        )}
                    </div>

                    {/* Stock */}
                    <p className="mt-3 text-xs tracking-[0.18em] uppercase">
                        {product.stock > 0 ? (
                            <span className="text-olive">
                                En stock — {product.stock} disponible
                                {product.stock > 1 ? 's' : ''}
                            </span>
                        ) : (
                            <span className="text-destructive">Épuisé</span>
                        )}
                    </p>

                    {/* Description */}
                    {product.description && (
                        <p className="mt-6 leading-relaxed text-ink-soft">
                            {product.description}
                        </p>
                    )}

                    {/* Quantity + actions */}
                    <div className="mt-8 space-y-4">
                        <div className="flex items-center gap-4">
                            <span className="eyebrow">Quantité</span>
                            <div className="inline-flex items-center border border-ink/30">
                                <button
                                    onClick={() =>
                                        setQty((q) => Math.max(1, q - 1))
                                    }
                                    className="px-3 py-2 transition-colors hover:bg-ink hover:text-cream disabled:hover:bg-transparent disabled:hover:text-ink/30"
                                    aria-label="Diminuer"
                                    disabled={qty <= 1}
                                >
                                    <Minus className="h-3.5 w-3.5" />
                                </button>
                                <span className="px-4 text-sm tabular-nums">
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
                                    className="px-3 py-2 transition-colors hover:bg-ink hover:text-cream disabled:hover:bg-transparent disabled:hover:text-ink/30"
                                    aria-label="Augmenter"
                                    disabled={qty >= product.stock}
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Button
                                disabled={product.stock === 0}
                                onClick={inCart ? goToCart : handleAddToCart}
                                className="h-12 flex-1 rounded-none bg-ink text-cream hover:bg-terracotta"
                            >
                                <ShoppingBag className="me-2 h-4 w-4" />

                                {inCart
                                    ? 'Passer au paiement'
                                    : 'Ajouter au panier'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setWished((w) => !w)}
                                className={`h-12 rounded-none border-ink ${
                                    wished ? 'bg-ink text-cream' : 'text-ink'
                                }`}
                            >
                                <Heart
                                    className={`me-2 h-4 w-4 ${wished ? 'fill-cream' : ''}`}
                                />
                                {wished ? 'Dans la wishlist' : 'Wishlist'}
                                <span className="ms-2 text-xs text-ink-soft">
                                    (
                                    {wishlistsCount +
                                        (wished && !initialWish ? 1 : 0)}
                                    )
                                </span>
                            </Button>
                        </div>
                    </div>

                    {/* Trust strip */}
                    <div className="mt-10 grid grid-cols-2 gap-4 border-t border-ink/10 pt-6 text-xs text-ink-soft">
                        <div className="flex items-start gap-2">
                            <Truck className="mt-0.5 h-4 w-4 text-ink" />
                            <span>
                                Livraison Amana sous 3–7 jours partout au Maroc.
                            </span>
                        </div>
                        <div className="flex items-start gap-2">
                            <ShieldCheck className="mt-0.5 h-4 w-4 text-ink" />
                            <span>
                                Pièce authentique, garantie de l'atelier.
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            {/* Tabs: details + reviews */}
            <section className="container pb-20">
                <Tabs defaultValue="reviews" className="w-full">
                    <TabsList className="h-auto w-full justify-start gap-6 rounded-none border-b border-ink/15 bg-transparent p-0">
                        <TabsTrigger
                            value="reviews"
                            className="rounded-none px-0 pb-3 text-sm tracking-[0.18em] uppercase data-[state=active]:border-b-2 data-[state=active]:border-ink data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                        >
                            Avis ({mockReviews.length})
                        </TabsTrigger>
                        <TabsTrigger
                            value="atelier"
                            className="rounded-none px-0 pb-3 text-sm tracking-[0.18em] uppercase data-[state=active]:border-b-2 data-[state=active]:border-ink data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                        >
                            L'atelier
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="reviews" className="mt-8">
                        <div className="grid grid-cols-12 gap-8">
                            {/* Rating summary */}
                            <div className="col-span-12 md:col-span-3">
                                <p className="font-display text-6xl leading-none text-ink">
                                    {rating.toFixed(1)}
                                </p>
                                <div className="mt-2 flex gap-0.5">
                                    {[1, 2, 3, 4, 5].map((n) => (
                                        <Star
                                            key={n}
                                            className={`h-4 w-4 ${
                                                n <= Math.round(rating)
                                                    ? 'fill-terracotta text-terracotta'
                                                    : 'text-ink/20'
                                            }`}
                                            strokeWidth={1.5}
                                        />
                                    ))}
                                </div>
                                <p className="mt-2 text-xs text-ink-soft">
                                    basé sur {mockReviews.length} avis
                                </p>
                            </div>

                            {/* Review list */}
                            <ul className="col-span-12 divide-y divide-ink/10 md:col-span-9">
                                {mockReviews.map((r) => (
                                    <li key={r.id} className="py-5 first:pt-0">
                                        <div className="flex items-center justify-between gap-4">
                                            <p className="font-display text-lg text-ink">
                                                {r.author}
                                            </p>
                                            <span className="text-xs text-ink-soft">
                                                {r.date}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex gap-0.5">
                                            {[1, 2, 3, 4, 5].map((n) => (
                                                <Star
                                                    key={n}
                                                    className={`h-3.5 w-3.5 ${
                                                        n <= r.rating
                                                            ? 'fill-terracotta text-terracotta'
                                                            : 'text-ink/20'
                                                    }`}
                                                    strokeWidth={1.5}
                                                />
                                            ))}
                                        </div>
                                        <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                                            {r.body}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </TabsContent>

                    <TabsContent value="atelier" className="mt-8 max-w-2xl">
                        <p className="mb-2 eyebrow">
                            {product.store.store_name}
                        </p>
                        <h3 className="mb-3 font-display text-2xl text-ink">
                            {product.store.city_name &&
                                ` · ${product.store.city_name}`}
                        </h3>
                        <p className="leading-relaxed text-ink-soft">
                            {product.store.description ??
                                "Cet atelier n'a pas encore rédigé sa présentation."}
                        </p>
                        <Link
                            href={`/seller/${product.store.slug}`}
                            className="link-underline mt-4 inline-block text-ink"
                        >
                            Voir la boutique →
                        </Link>
                    </TabsContent>
                </Tabs>
            </section>

            {/* More from store */}
            {moreFromStore.length > 0 && (
                <section className="container pb-24">
                    <div className="mb-8 flex items-end justify-between">
                        <div>
                            <p className="mb-2 eyebrow">Du même atelier</p>
                            <h2 className="h-editorial text-3xl text-ink md:text-4xl">
                                Autres pièces de {product.store.store_name}
                            </h2>
                        </div>
                        <Link
                            href={`/seller/${product.store.slug}`}
                            className="link-underline hidden text-sm text-ink md:inline"
                        >
                            Voir tout →
                        </Link>
                    </div>

                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-6">
                        {moreFromStore.map((p) => {
                            const cover = p.images[0]?.url;
                            const sale =
                                p.sale_price &&
                                Number(p.sale_price) < Number(p.base_price);

                            return (
                                <Link
                                    key={p.id}
                                    href={`/products/${p.slug}`}
                                    className="group block"
                                >
                                    <div className="relative aspect-4/5 overflow-hidden bg-ink/5">
                                        {cover && (
                                            <img
                                                src={cover}
                                                alt={p.name}
                                                loading="lazy"
                                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                                            />
                                        )}
                                    </div>
                                    <p className="mt-2 text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                                        {p.category?.translated_name}
                                    </p>
                                    <h3 className="font-display text-base leading-tight text-ink transition-colors group-hover:text-terracotta">
                                        {p.name}
                                    </h3>
                                    <p className="mt-1 text-sm">
                                        {sale ? (
                                            <>
                                                <span className="text-terracotta">
                                                    {Number(p.sale_price)} MAD
                                                </span>{' '}
                                                <span className="text-xs text-ink-soft line-through">
                                                    {Number(p.base_price)} MAD
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-ink">
                                                {Number(p.base_price)} MAD
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
