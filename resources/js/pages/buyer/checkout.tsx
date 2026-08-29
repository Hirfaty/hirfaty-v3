import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck, Truck, AlertCircle } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { Separator } from '@/components/ui/separator';
import type { Cart, CartItem, City, Product } from '@/types';
import { Store } from '@/types';

const PLATFORM_FEE_PERCENT = 0.2;
const AMANA_FEE = 20;

const Checkout = ({ cart, cities }: { cart: Cart; cities: City[] }) => {
    // console.log('cart: ', cart);
    const { data, setData, post, processing, errors } = useForm({
        shipping_address: '',
        city_id: cities[0]?.id ?? null,
        notes: '',
    });

    const formatMAD = (value: number) =>
        new Intl.NumberFormat('fr-MA', {
            style: 'currency',
            currency: 'MAD',
        }).format(value);

    const getPrice = (product: Product) =>
        parseFloat(product.sale_price ?? product.base_price);

    // Group by store
    const grouped = cart.items.reduce<
        Record<number, { storeName: string; items: CartItem[] }>
    >((acc, item) => {
        const storeId = item.product.store.id;

        if (!acc[storeId]) {
            acc[storeId] = {
                storeName: item.product.store.store_name,
                items: [],
            };
        }

        acc[storeId].items.push(item);

        return acc;
    }, {});

    const storeCount = Object.keys(grouped).length;
    const productsTotal = cart.items.reduce(
        (sum, item) => sum + getPrice(item.product) * item.quantity,
        0,
    );
    const totalPlatformFee = parseFloat(
        (productsTotal * PLATFORM_FEE_PERCENT).toFixed(2),
    );
    const totalAmanaFee = AMANA_FEE * storeCount;
    const grandTotal = productsTotal + totalAmanaFee;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        toast.loading('Traitement de votre commande en cours...');
        post(route('checkout.store'));
    };

    return (
        <div className="paper-grain min-h-screen bg-cream font-sans text-ink">
            <SiteHeader />

            <section className="container pt-12 pb-6">
                <Link
                    href={route('cart.show')}
                    className="inline-flex items-center gap-2 text-sm text-ink-soft transition-colors hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour au panier
                </Link>

                <h1 className="h-editorial mt-6 text-4xl md:text-5xl">
                    Finaliser la commande
                </h1>
            </section>

            <section className="container pb-24">
                {/* {errors.cart && (
                    <div className="mb-8 flex animate-in items-start gap-3 border border-terracotta/20 bg-terracotta/5 p-4 text-sm text-terracotta fade-in slide-in-from-top-2">
                        <AlertCircle className="h-5 w-5 shrink-0" />
                        <p>{errors.cart}</p>
                    </div>
                )} */}

                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-12 gap-12"
                >
                    {/* ── Left — form ── */}
                    <div className="col-span-12 space-y-10 lg:col-span-7">
                        {/* Shipping address */}
                        <div className="space-y-4">
                            <h2 className="border-b border-ink/10 pb-2 font-display text-xl">
                                Informations de livraison
                            </h2>

                            <div className="space-y-4">
                                <div>
                                    <label className="eyebrow mb-2 block">
                                        Ville
                                    </label>
                                    <select
                                        value={data.city_id ?? ''}
                                        onChange={(e) =>
                                            setData(
                                                'city_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        className="w-full border border-ink/10 bg-paper p-3 text-sm transition-colors focus:border-ink focus:outline-none"
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
                                    {errors.city_id && (
                                        <p className="mt-1 text-xs text-terracotta">
                                            {errors.city_id}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="eyebrow mb-2 block">
                                        Adresse complète
                                    </label>
                                    <textarea
                                        value={data.shipping_address}
                                        onChange={(e) =>
                                            setData(
                                                'shipping_address',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                        placeholder="Rue, quartier, numéro de maison..."
                                        className="w-full border border-ink/10 bg-paper p-3 text-sm transition-colors focus:border-ink focus:outline-none"
                                    />
                                    {errors.shipping_address && (
                                        <p className="mt-1 text-xs text-terracotta">
                                            {errors.shipping_address}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="eyebrow mb-2 block">
                                        Notes{' '}
                                        <span className="font-normal text-ink-soft">
                                            (optionnel)
                                        </span>
                                    </label>
                                    <textarea
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData('notes', e.target.value)
                                        }
                                        rows={2}
                                        placeholder="Instructions pour le livreur..."
                                        className="w-full border border-ink/10 bg-paper p-3 text-sm transition-colors focus:border-ink focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Orders breakdown per store */}
                        <div className="space-y-6">
                            <h2 className="border-b border-ink/10 pb-2 font-display text-xl">
                                Détail des expéditions
                            </h2>
                            <p className="text-sm text-ink-soft italic">
                                Votre panier contient des articles de{' '}
                                {storeCount} artisan{storeCount > 1 ? 's' : ''}{' '}
                                différent{storeCount > 1 ? 's' : ''}. Vous
                                recevrez {storeCount} colis distinct
                                {storeCount > 1 ? 's' : ''}.
                            </p>

                            <div className="space-y-6">
                                {Object.entries(grouped).map(
                                    ([storeId, { storeName, items }]) => (
                                        <div
                                            key={storeId}
                                            className="border border-ink/5 bg-cream-deep/30 p-6"
                                        >
                                            <p className="eyebrow mb-4 text-xs">
                                                Expédié par {storeName}
                                            </p>
                                            <ul className="space-y-3">
                                                {items.map((item) => (
                                                    <li
                                                        key={item.id}
                                                        className="flex items-center justify-between"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div className="h-10 w-10 flex-shrink-0 overflow-hidden border border-ink/10 bg-paper">
                                                                {item.product
                                                                    .images[0] && (
                                                                    <img
                                                                        src={
                                                                            item
                                                                                .product
                                                                                .images[0]
                                                                                .url
                                                                        }
                                                                        alt=""
                                                                        className="h-full w-full object-cover"
                                                                    />
                                                                )}
                                                            </div>
                                                            <div className="flex flex-col">
                                                                <span className="text-sm font-medium">
                                                                    {
                                                                        item
                                                                            .product
                                                                            .name
                                                                    }
                                                                </span>
                                                                <span className="text-xs text-ink-soft">
                                                                    Quantité :{' '}
                                                                    {
                                                                        item.quantity
                                                                    }
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <span className="font-display text-sm">
                                                            {formatMAD(
                                                                getPrice(
                                                                    item.product,
                                                                ) *
                                                                    item.quantity,
                                                            )}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>

                                            <div className="mt-4 flex items-center justify-between border-t border-ink/5 pt-4 text-xs text-ink-soft">
                                                <div className="flex items-center gap-1.5">
                                                    <Truck className="h-3 w-3" />
                                                    <span>
                                                        Livraison Amana
                                                        (Standard)
                                                    </span>
                                                </div>
                                                <span>
                                                    {formatMAD(AMANA_FEE)}
                                                </span>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ── Right — summary ── */}
                    <aside className="col-span-12 lg:col-span-5">
                        <div className="sticky top-24 border border-ink/15 bg-paper p-8 shadow-sm">
                            <h2 className="eyebrow mb-6">Récapitulatif</h2>

                            <div className="space-y-4 text-sm">
                                <div className="flex justify-between text-ink-soft">
                                    <span>Produits ({cart.items.length})</span>
                                    <span>{formatMAD(productsTotal)}</span>
                                </div>
                                <div className="flex justify-between text-ink-soft">
                                    <span>
                                        Frais de livraison ({storeCount} colis)
                                    </span>
                                    <span>{formatMAD(totalAmanaFee)}</span>
                                </div>

                                <Separator className="bg-ink/10" />

                                <div className="flex items-baseline justify-between pt-2">
                                    <span className="font-display text-lg">
                                        Total
                                    </span>
                                    <span className="font-display text-3xl">
                                        {formatMAD(grandTotal)}
                                    </span>
                                </div>
                            </div>

                            <div className="mt-8 space-y-4">
                                <div className="flex gap-3 rounded-sm border border-ink/5 bg-cream p-4">
                                    <ShieldCheck className="h-5 w-5 shrink-0 text-ink" />
                                    <p className="text-xs leading-relaxed text-ink-soft">
                                        Paiement sécurisé à la livraison. Vous
                                        payez directement le livreur Amana à
                                        réception de vos colis.
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-ink py-4 font-display text-base text-cream transition-colors hover:bg-ink/90 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Traitement en cours...'
                                        : `Confirmer la commande • ${formatMAD(grandTotal)}`}
                                </button>
                            </div>
                        </div>
                    </aside>
                </form>
            </section>

            <SiteFooter />
        </div>
    );
};

export default Checkout;
