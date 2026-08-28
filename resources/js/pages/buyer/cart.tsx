import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Minus,
    Plus,
    Trash2,
    ShieldCheck,
    Truck,
    Heart,
    Tag,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import SiteHeader from '@/components/site/SiteHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import type { Cart } from '@/types';

const CartPage = ({ cart }: { cart: Cart }) => {
    const [promo, setPromo] = useState('');
    const [promoApplied, setPromoApplied] = useState(0);
    const [deletingItemId, setDeletingItemId] = useState<number | null>(null);

    const toPrice = (price: string | null) => Number(price ?? 0);
    const getUnitPrice = (product: any) =>
        toPrice(product.sale_price ?? product.base_price);
    const subtotal = cart.items.reduce((sum, item) => {
        return sum + getUnitPrice(item.product) * item.quantity;
    }, 0);

    const savings = cart.items.reduce((sum, item) => {
        if (!item.product.sale_price) {
            return sum;
        }

        const base = toPrice(item.product.base_price);
        const sale = toPrice(item.product.sale_price);

        return sum + (base - sale) * item.quantity;
    }, 0);

    const discount = Math.round((subtotal * promoApplied) / 100);
    const storeCount = new Set(cart.items.map((item) => item.product.store.id))
        .size;

    const shipping = 20 * storeCount;
    const total = subtotal - discount + shipping;

    // const applyPromo = () => {
    //     const percent = Number(promo.replace(/\D/g, ''));

    //     if (percent > 0 && percent <= 100) {
    //         setPromoApplied(percent);
    //         toast.success(`Code promo appliqué : ${percent}%`);
    //     } else {
    //         setPromoApplied(0);
    //         toast.error('Veuillez saisir un pourcentage valide.');
    //     }
    // };

    const changeQuantity = (cartItemId: number, quantity: number) => {
        if (quantity < 1) {
            return;
        }

        router.patch(
            route('cart.update', cartItemId),
            { quantity },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const deleteCartItem = (cartItemId: number) => {
        setDeletingItemId(cartItemId);
        router.delete(route('cart.remove', cartItemId), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setDeletingItemId(null),
        });
    };

    return (
        <div className="paper-grain min-h-screen bg-cream">
            <SiteHeader />
            <section className="container pt-12 pb-6">
                <Link
                    href="/products"
                    className="inline-flex items-center gap-2 text-sm text-ink-soft hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Continuer la découverte
                </Link>
                <div className="mt-6 grid grid-cols-12 items-end gap-6">
                    <div className="col-span-12 md:col-span-8">
                        <p className="eyebrow mb-3">Votre sélection</p>
                        <h1 className="h-editorial text-5xl text-ink md:text-6xl">
                            Le panier,{' '}
                            <span className="text-terracotta italic">
                                soigneusement composé
                            </span>
                            .
                        </h1>
                    </div>
                    <div className="col-span-12 text-sm text-ink-soft md:col-span-4 md:text-right">
                        {cart.items.length}{' '}
                        {cart.items.length > 1 ? 'pièces' : 'pièce'} ·{' '}
                        {cart.items.reduce((s, i) => s + i.quantity, 0)}{' '}
                        articles
                    </div>
                </div>
            </section>

            {cart.items.length === 0 ? (
                <section className="container max-w-xl py-24 text-center">
                    <p className="eyebrow mb-3">Panier vide</p>
                    <h2 className="h-editorial mb-4 text-4xl text-ink">
                        Rien encore choisi.
                    </h2>
                    <p className="mb-8 text-ink-soft">
                        Parcourez le marché et laissez-vous tenter par une pièce
                        d'artisan.
                    </p>
                    <Link
                        href="/browse"
                        className="inline-block border border-ink px-8 py-3 text-sm text-ink transition-colors hover:bg-ink hover:text-cream"
                    >
                        Explorer le marché
                    </Link>
                </section>
            ) : (
                <section className="container grid grid-cols-12 gap-8 pb-20 md:gap-12">
                    {/* Items */}
                    <div className="col-span-12 lg:col-span-8">
                        <div className="border-y border-ink/15">
                            {cart.items.map((item, i) => (
                                <article
                                    key={item.id}
                                    className={`grid grid-cols-12 gap-4 py-6 md:gap-6 ${i > 0 ? 'border-t border-ink/10' : ''}`}
                                >
                                    <Link
                                        href={`/products/${item.product.slug}`}
                                        className="relative col-span-3 aspect-square overflow-hidden bg-cream-deep/40 md:col-span-2"
                                    >
                                        <img
                                            src={
                                                item.product.images[0]?.url ??
                                                'https://hirfaty.com/static/media/llgg.c57ae82726ffff1bcc92.png'
                                            }
                                            alt={item.product.name}
                                            className="h-full w-full object-cover object-center"
                                        />
                                    </Link>

                                    <div className="col-span-9 flex flex-col md:col-span-7">
                                        <Link
                                            href={`/products/${item.product.slug}`}
                                            className="font-display text-lg leading-tight text-ink transition-colors hover:text-terracotta md:text-xl"
                                        >
                                            {item.product.name}
                                        </Link>
                                        <p className="mt-1 text-sm text-ink-soft">
                                            par {item.product.store.store_name}
                                        </p>

                                        <div className="mt-auto flex flex-wrap items-center gap-4 pt-4 text-xs">
                                            <div className="inline-flex items-center border border-ink/20">
                                                <button
                                                    aria-label="Diminuer"
                                                    onClick={() =>
                                                        changeQuantity(
                                                            item.id,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                    disabled={
                                                        item.quantity <= 1
                                                    }
                                                    className="inline-flex h-8 w-8 items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
                                                >
                                                    <Minus
                                                        className="h-3 w-3"
                                                        strokeWidth={1.5}
                                                    />
                                                </button>
                                                <span className="w-8 text-center font-display text-sm text-ink">
                                                    {item.quantity}
                                                </span>
                                                <button
                                                    aria-label="Augmenter"
                                                    onClick={() =>
                                                        changeQuantity(
                                                            item.id,
                                                            item.quantity + 1,
                                                        )
                                                    }
                                                    disabled={
                                                        item.quantity >=
                                                        item.product.stock
                                                    }
                                                    className="inline-flex h-8 w-8 items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
                                                >
                                                    <Plus
                                                        className="h-3 w-3"
                                                        strokeWidth={1.5}
                                                    />
                                                </button>
                                            </div>

                                            <button
                                                // onClick={() =>
                                                //     moveToWishlist(item.id)
                                                // }
                                                className="inline-flex items-center gap-1.5 text-ink-soft transition-colors hover:text-ink"
                                            >
                                                <Heart
                                                    className="h-3.5 w-3.5"
                                                    strokeWidth={1.5}
                                                />{' '}
                                                Mettre de côté
                                            </button>

                                            <button
                                                onClick={() =>
                                                    deleteCartItem(item.id)
                                                }
                                                disabled={
                                                    deletingItemId === item.id
                                                }
                                                className="inline-flex items-center gap-1.5 text-ink-soft transition-colors hover:text-terracotta disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {deletingItemId === item.id ? (
                                                    <Spinner className="h-3.5 w-3.5 text-ink-soft" />
                                                ) : (
                                                    <Trash2
                                                        className="h-3.5 w-3.5"
                                                        strokeWidth={1.5}
                                                    />
                                                )}{' '}
                                                Retirer
                                            </button>

                                            {item.product.stock <= 6 && (
                                                <span className="text-terracotta">
                                                    Plus que{' '}
                                                    {item.product.stock} en
                                                    atelier
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="col-span-12 flex justify-between gap-1 md:col-span-3 md:flex-col md:items-end md:justify-start">
                                        <div className="md:text-right">
                                            {item.product.sale_price ? (
                                                <>
                                                    <div className="font-display text-2xl text-ink">
                                                        {Number(
                                                            item.product
                                                                .sale_price,
                                                        ) * item.quantity}{' '}
                                                        <span className="text-sm">
                                                            MAD
                                                        </span>
                                                    </div>
                                                    <div className="text-xs text-ink-soft line-through">
                                                        {Number(
                                                            item.product
                                                                .base_price,
                                                        ) * item.quantity}{' '}
                                                        MAD
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="font-display text-2xl text-ink">
                                                    {Number(
                                                        item.product.base_price,
                                                    ) * item.quantity}{' '}
                                                    <span className="text-sm">
                                                        MAD
                                                    </span>
                                                </div>
                                            )}
                                            <div className="mt-0.5 text-[11px] text-ink-soft">
                                                {Number(
                                                    item.product.sale_price ??
                                                        item.product.base_price,
                                                )}{' '}
                                                MAD / pièce
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>

                        <div className="mt-8 flex items-center gap-2 text-sm text-ink-soft">
                            <Truck className="h-4 w-4" strokeWidth={1.5} />
                            {subtotal >= 600 ? (
                                <span>
                                    Livraison offerte sur cette commande.
                                </span>
                            ) : (
                                <span>
                                    Encore{' '}
                                    <span className="font-medium text-ink">
                                        {600 - subtotal} MAD
                                    </span>{' '}
                                    pour la livraison offerte.
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Summary */}
                    <aside className="col-span-12 lg:col-span-4">
                        <div className="border border-ink/15 bg-paper p-6 md:p-8 lg:sticky lg:top-6">
                            <p className="eyebrow mb-5">Récapitulatif</p>

                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between text-ink-soft">
                                    <dt>Sous-total</dt>
                                    <dd className="text-ink">{subtotal} MAD</dd>
                                </div>
                                {savings > 0 && (
                                    <div className="flex justify-between text-terracotta">
                                        <dt>Économies artisans</dt>
                                        <dd>− {savings} MAD</dd>
                                    </div>
                                )}
                                {/* {promoApplied > 0 && (
                                    <div className="flex justify-between text-terracotta">
                                        <dt>Code promo ({promoApplied}%)</dt>
                                        <dd>− {discount} MAD</dd>
                                    </div>
                                )} */}
                                <div className="flex justify-between text-ink-soft">
                                    <dt>Livraison estimée</dt>
                                    <dd>
                                        {shipping === 0
                                            ? 'Gratuit'
                                            : `${shipping} MAD`}
                                    </dd>
                                </div>
                            </dl>

                            <Separator className="my-5 bg-ink/15" />

                            <div className="flex items-baseline justify-between">
                                <span className="eyebrow">Total</span>
                                <span className="font-display text-3xl text-ink">
                                    {total}{' '}
                                    <span className="text-base">MAD</span>
                                </span>
                            </div>

                            <Link
                                href="/checkout"
                                className="mt-6 block w-full bg-ink py-3 text-center font-display text-sm text-cream transition-colors hover:bg-ink/90"
                            >
                                Passer commande
                            </Link>

                            {/* <div className="mt-6">
                                <label
                                    htmlFor="promo"
                                    className="eyebrow mb-2 block text-[10px]"
                                >
                                    Code promo
                                </label>
                                <div className="flex gap-2">
                                    <div className="relative flex-1">
                                        <Tag
                                            className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-ink-soft"
                                            strokeWidth={1.5}
                                        />
                                        <Input
                                            id="promo"
                                            value={promo}
                                            onChange={(e) =>
                                                setPromo(e.target.value)
                                            }
                                            placeholder="HIRFATY10"
                                            className="rounded-none border-ink/20 bg-cream pl-8"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={applyPromo}
                                        variant="outline"
                                        className="rounded-none border-ink text-ink hover:bg-ink hover:text-cream"
                                    >
                                        Appliquer
                                    </Button>
                                </div>
                            </div> */}

                            <div className="mt-6 flex items-start gap-2 text-xs text-ink-soft">
                                <ShieldCheck
                                    className="mt-0.5 h-4 w-4 shrink-0"
                                    strokeWidth={1.5}
                                />
                                <p>
                                    Paiement sécurisé. Remboursement intégral si
                                    la pièce ne correspond pas à la photo de
                                    l'artisan.
                                </p>
                            </div>
                        </div>
                    </aside>
                </section>
            )}
        </div>
    );

    // console.log('cart: ', cart);
    // const [promoApplied, setPromoApplied] = useState<number>(0);
    // const [deletingItemId, setDeletingItemId] = useState<number | null>(null);

    // // ✅ helpers (VERY IMPORTANT)
    // const toPrice = (price: string | null) => Number(price ?? 0);

    // const getUnitPrice = (product: any) =>
    //     toPrice(product.sale_price ?? product.base_price);

    // const getBasePrice = (product: any) => toPrice(product.base_price);

    // const formatMAD = (value: number) =>
    //     new Intl.NumberFormat('fr-MA', {
    //         style: 'currency',
    //         currency: 'MAD',
    //     }).format(value);

    // // ✅ calculations
    // const subtotal = cart.items.reduce((sum, item) => {
    //     return sum + getUnitPrice(item.product) * item.quantity;
    // }, 0);

    // const savings = cart.items.reduce((sum, item) => {
    //     if (!item.product.sale_price) {
    //         return sum;
    //     }

    //     const base = getBasePrice(item.product);
    //     const sale = toPrice(item.product.sale_price);

    //     return sum + (base - sale) * item.quantity;
    // }, 0);

    // const discount = Math.round((subtotal * promoApplied) / 100);
    // // const shipping = subtotal > 600 ? 0 : 45;
    // //shipping should be 20 per store, so if the cart has items from 2 different stores, shipping should be 40, etc... but for simplicity, we'll do 20 per item
    // const shipping =
    //     20 * cart.items.reduce((sum, item) => sum + item.quantity, 0); // 20 MAD per item
    // const total = subtotal - discount + shipping;

    // const changeQuantity = (cartItemId: number, quantity: number) => {
    //     router.patch(route('cart.update', cartItemId), { quantity: quantity });
    // };

    // const deleteCartItem = (cartItemId: number) => {
    //     setDeletingItemId(cartItemId);
    //     router.delete(route('cart.remove', cartItemId));
    // };

    // return (
    //     <div className="paper-grain min-h-screen bg-cream">
    //         <SiteHeader />

    //         <section className="container pt-12 pb-6">
    //             <Link
    //                 href="/products"
    //                 className="inline-flex items-center gap-2 text-sm text-ink-soft hover:text-ink"
    //             >
    //                 <ArrowLeft className="h-4 w-4" /> Continuer la découverte
    //             </Link>

    //             <div className="mt-6 flex justify-between">
    //                 <h1 className="text-4xl">Panier</h1>
    //                 <span>{cart.items.length} articles</span>
    //             </div>
    //         </section>

    //         {cart.items.length === 0 ? (
    //             <section className="container py-24 text-center">
    //                 <p>Panier vide</p>
    //             </section>
    //         ) : (
    //             <section className="container grid grid-cols-12 gap-8 pb-20">
    //                 {/* ITEMS */}
    //                 <div className="col-span-12 lg:col-span-8">
    //                     <div className="border-y border-ink/15">
    //                         {cart.items.map((item, i) => (
    //                             <article
    //                                 key={item.id}
    //                                 className={`grid grid-cols-12 gap-4 py-6 ${i > 0 ? 'border-t border-ink/10' : ''}`}
    //                             >
    //                                 <div className="col-span-9 flex flex-col">
    //                                     <Link
    //                                         href={`/products/${item.product.slug}`}
    //                                         className="text-lg text-ink hover:text-terracotta"
    //                                     >
    //                                         {item.product.name}
    //                                     </Link>

    //                                     <p className="text-sm text-ink-soft">
    //                                         par {item.product.store.store_name}
    //                                     </p>

    //                                     <div className="mt-4 flex items-center gap-4">
    //                                         <div className="flex items-center border">
    //                                             <button
    //                                                 onClick={() =>
    //                                                     changeQuantity(
    //                                                         item.id,
    //                                                         item.quantity - 1,
    //                                                     )
    //                                                 }
    //                                                 disabled={
    //                                                     item.quantity == 1
    //                                                 }
    //                                                 className="disabled:pointer-none: inline-flex h-8 w-8 cursor-pointer items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
    //                                             >
    //                                                 <Minus className="h-3 w-3" />
    //                                             </button>

    //                                             <span className="w-8 text-center font-display text-sm text-ink">
    //                                                 {item.quantity}
    //                                             </span>

    //                                             <button
    //                                                 onClick={() =>
    //                                                     changeQuantity(
    //                                                         item.id,
    //                                                         item.quantity + 1,
    //                                                     )
    //                                                 }
    //                                                 disabled={
    //                                                     item.quantity >=
    //                                                     item.product.stock
    //                                                 }
    //                                                 className="inline-flex h-8 w-8 cursor-pointer items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
    //                                             >
    //                                                 <Plus className="h-3 w-3" />
    //                                             </button>
    //                                         </div>

    //                                         <button
    //                                             onClick={() =>
    //                                                 deleteCartItem(item.id)
    //                                             }
    //                                             disabled={
    //                                                 deletingItemId === item.id
    //                                             }
    //                                             className="inline-flex cursor-pointer items-center gap-2 text-sm text-ink-soft hover:text-terracotta disabled:cursor-not-allowed disabled:opacity-50"
    //                                         >
    //                                             {deletingItemId === item.id ? (
    //                                                 <Spinner className="h-4 w-4 text-ink-soft" />
    //                                             ) : (
    //                                                 <Trash2 className="inline h-4 w-4" />
    //                                             )}
    //                                             Retirer
    //                                         </button>

    //                                         <button className="text-sm text-ink-soft hover:text-ink">
    //                                             <Heart className="inline h-4 w-4" />{' '}
    //                                             Favoris
    //                                         </button>
    //                                     </div>
    //                                 </div>

    //                                 {/* ✅ PRICE BLOCK FIXED */}
    //                                 <div className="col-span-3 text-right">
    //                                     {item.product.sale_price ? (
    //                                         <>
    //                                             <div className="text-xl text-ink">
    //                                                 {formatMAD(
    //                                                     getUnitPrice(
    //                                                         item.product,
    //                                                     ) * item.quantity,
    //                                                 )}
    //                                             </div>

    //                                             <div className="text-xs text-ink-soft line-through">
    //                                                 {formatMAD(
    //                                                     getBasePrice(
    //                                                         item.product,
    //                                                     ) * item.quantity,
    //                                                 )}
    //                                             </div>
    //                                         </>
    //                                     ) : (
    //                                         <div className="text-xl text-ink">
    //                                             {formatMAD(
    //                                                 getBasePrice(item.product) *
    //                                                     item.quantity,
    //                                             )}
    //                                         </div>
    //                                     )}

    //                                     <div className="text-xs text-ink-soft">
    //                                         {formatMAD(
    //                                             getUnitPrice(item.product),
    //                                         )}{' '}
    //                                         / pièce
    //                                     </div>
    //                                 </div>
    //                             </article>
    //                         ))}
    //                     </div>

    //                     <div className="mt-6 flex items-center gap-2 text-sm text-ink-soft">
    //                         <Truck className="h-4 w-4" />
    //                         {subtotal >= 600 ? (
    //                             <span>Livraison offerte</span>
    //                         ) : (
    //                             <span>
    //                                 Encore {formatMAD(600 - subtotal)} pour
    //                                 livraison gratuite
    //                             </span>
    //                         )}
    //                     </div>
    //                 </div>

    //                 {/* SUMMARY */}
    //                 <aside className="col-span-12 lg:col-span-4">
    //                     <div className="border p-6">
    //                         <p className="mb-4">Résumé</p>

    //                         <div className="space-y-2 text-sm">
    //                             <div className="flex justify-between">
    //                                 <span>Sous-total</span>
    //                                 <span>{formatMAD(subtotal)}</span>
    //                             </div>

    //                             {savings > 0 && (
    //                                 <div className="flex justify-between text-terracotta">
    //                                     <span>Économies</span>
    //                                     <span>-{formatMAD(savings)}</span>
    //                                 </div>
    //                             )}

    //                             <div className="flex justify-between">
    //                                 <span>Livraison</span>
    //                                 <span>
    //                                     {shipping === 0
    //                                         ? 'Gratuit'
    //                                         : formatMAD(shipping)}
    //                                 </span>
    //                             </div>
    //                         </div>

    //                         <Separator className="my-4" />

    //                         <div className="flex justify-between text-lg">
    //                             <span>Total</span>
    //                             <span>{formatMAD(total)}</span>
    //                         </div>

    //                         <Link
    //                             href="/checkout"
    //                             className="mt-4 block bg-black py-3 text-center text-white"
    //                         >
    //                             Checkout
    //                         </Link>

    //                         <div className="mt-4 flex gap-2 text-xs text-ink-soft">
    //                             <ShieldCheck className="h-4 w-4" />
    //                             Paiement sécurisé
    //                         </div>
    //                     </div>
    //                 </aside>
    //             </section>
    //         )}

    //         <SiteFooter />
    //     </div>
    // );
};

export default CartPage;
