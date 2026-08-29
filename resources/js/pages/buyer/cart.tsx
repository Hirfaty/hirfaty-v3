import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Minus,
    Plus,
    Trash2,
    ShieldCheck,
    Truck,
    Heart,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import type { Cart } from '@/types';
import { CartItem } from '@/types';

const CartPage = ({ cart }: { cart: Cart }) => {
    console.log('cart: ', cart);
    const [promoApplied, setPromoApplied] = useState<number>(0);
    const [deletingItemId, setDeletingItemId] = useState<number | null>(null);

    // ✅ helpers (VERY IMPORTANT)
    const toPrice = (price: string | null) => Number(price ?? 0);

    const getUnitPrice = (product: any) =>
        toPrice(product.sale_price ?? product.base_price);

    const getBasePrice = (product: any) => toPrice(product.base_price);

    const formatMAD = (value: number) =>
        new Intl.NumberFormat('fr-MA', {
            style: 'currency',
            currency: 'MAD',
        }).format(value);

    // ✅ calculations
    const subtotal = cart.items.reduce((sum, item) => {
        return sum + getUnitPrice(item.product) * item.quantity;
    }, 0);

    const savings = cart.items.reduce((sum, item) => {
        if (!item.product.sale_price) {
            return sum;
        }

        const base = getBasePrice(item.product);
        const sale = toPrice(item.product.sale_price);

        return sum + (base - sale) * item.quantity;
    }, 0);

    const discount = Math.round((subtotal * promoApplied) / 100);
    // const shipping = subtotal > 600 ? 0 : 45;
    //shipping should be 20 per store, so if the cart has items from 2 different stores, shipping should be 40, etc... but for simplicity, we'll do 20 per item
    const shipping =
        20 * cart.items.reduce((sum, item) => sum + item.quantity, 0); // 20 MAD per item
    const total = subtotal - discount + shipping;

    const changeQuantity = (cartItemId: number, quantity: number) => {
        router.patch(route('cart.update', cartItemId), { quantity: quantity });
    };

    const deleteCartItem = (cartItemId: number) => {
        setDeletingItemId(cartItemId);
        router.delete(route('cart.remove', cartItemId));
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

                <div className="mt-6 flex justify-between">
                    <h1 className="text-4xl">Panier</h1>
                    <span>{cart.items.length} articles</span>
                </div>
            </section>

            {cart.items.length === 0 ? (
                <section className="container py-24 text-center">
                    <p>Panier vide</p>
                </section>
            ) : (
                <section className="container grid grid-cols-12 gap-8 pb-20">
                    {/* ITEMS */}
                    <div className="col-span-12 lg:col-span-8">
                        <div className="border-y border-ink/15">
                            {cart.items.map((item, i) => (
                                <article
                                    key={item.id}
                                    className={`grid grid-cols-12 gap-4 py-6 ${i > 0 ? 'border-t border-ink/10' : ''}`}
                                >
                                    <div className="col-span-9 flex flex-col">
                                        <Link
                                            href={`/products/${item.product.slug}`}
                                            className="text-lg text-ink hover:text-terracotta"
                                        >
                                            {item.product.name}
                                        </Link>

                                        <p className="text-sm text-ink-soft">
                                            par {item.product.store.store_name}
                                        </p>

                                        <div className="mt-4 flex items-center gap-4">
                                            <div className="flex items-center border">
                                                <button
                                                    onClick={() =>
                                                        changeQuantity(
                                                            item.id,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                    disabled={
                                                        item.quantity == 1
                                                    }
                                                    className="disabled:pointer-none: inline-flex h-8 w-8 cursor-pointer items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
                                                >
                                                    <Minus className="h-3 w-3" />
                                                </button>

                                                <span className="w-8 text-center font-display text-sm text-ink">
                                                    {item.quantity}
                                                </span>

                                                <button
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
                                                    className="inline-flex h-8 w-8 cursor-pointer items-center justify-center text-ink-soft hover:bg-cream-deep/60 hover:text-ink disabled:opacity-30"
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </button>
                                            </div>

                                            <button
                                                onClick={() =>
                                                    deleteCartItem(item.id)
                                                }
                                                disabled={
                                                    deletingItemId === item.id
                                                }
                                                className="inline-flex cursor-pointer items-center gap-2 text-sm text-ink-soft hover:text-terracotta disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {deletingItemId === item.id ? (
                                                    <Spinner className="h-4 w-4 text-ink-soft" />
                                                ) : (
                                                    <Trash2 className="inline h-4 w-4" />
                                                )}
                                                Retirer
                                            </button>

                                            <button className="text-sm text-ink-soft hover:text-ink">
                                                <Heart className="inline h-4 w-4" />{' '}
                                                Favoris
                                            </button>
                                        </div>
                                    </div>

                                    {/* ✅ PRICE BLOCK FIXED */}
                                    <div className="col-span-3 text-right">
                                        {item.product.sale_price ? (
                                            <>
                                                <div className="text-xl text-ink">
                                                    {formatMAD(
                                                        getUnitPrice(
                                                            item.product,
                                                        ) * item.quantity,
                                                    )}
                                                </div>

                                                <div className="text-xs text-ink-soft line-through">
                                                    {formatMAD(
                                                        getBasePrice(
                                                            item.product,
                                                        ) * item.quantity,
                                                    )}
                                                </div>
                                            </>
                                        ) : (
                                            <div className="text-xl text-ink">
                                                {formatMAD(
                                                    getBasePrice(item.product) *
                                                        item.quantity,
                                                )}
                                            </div>
                                        )}

                                        <div className="text-xs text-ink-soft">
                                            {formatMAD(
                                                getUnitPrice(item.product),
                                            )}{' '}
                                            / pièce
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>

                        <div className="mt-6 flex items-center gap-2 text-sm text-ink-soft">
                            <Truck className="h-4 w-4" />
                            {subtotal >= 600 ? (
                                <span>Livraison offerte</span>
                            ) : (
                                <span>
                                    Encore {formatMAD(600 - subtotal)} pour
                                    livraison gratuite
                                </span>
                            )}
                        </div>
                    </div>

                    {/* SUMMARY */}
                    <aside className="col-span-12 lg:col-span-4">
                        <div className="border p-6">
                            <p className="mb-4">Résumé</p>

                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span>Sous-total</span>
                                    <span>{formatMAD(subtotal)}</span>
                                </div>

                                {savings > 0 && (
                                    <div className="flex justify-between text-terracotta">
                                        <span>Économies</span>
                                        <span>-{formatMAD(savings)}</span>
                                    </div>
                                )}

                                <div className="flex justify-between">
                                    <span>Livraison</span>
                                    <span>
                                        {shipping === 0
                                            ? 'Gratuit'
                                            : formatMAD(shipping)}
                                    </span>
                                </div>
                            </div>

                            <Separator className="my-4" />

                            <div className="flex justify-between text-lg">
                                <span>Total</span>
                                <span>{formatMAD(total)}</span>
                            </div>

                            <Link
                                href="/checkout"
                                className="mt-4 block bg-black py-3 text-center text-white"
                            >
                                Checkout
                            </Link>

                            <div className="mt-4 flex gap-2 text-xs text-ink-soft">
                                <ShieldCheck className="h-4 w-4" />
                                Paiement sécurisé
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
