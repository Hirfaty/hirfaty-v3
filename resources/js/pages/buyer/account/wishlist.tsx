import React from 'react';
import { Link } from '@inertiajs/react';
import { Heart, ShoppingBag, ArrowRight, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import BuyerLayout from '@/layouts/buyer/layout';
import type { BuyerProductResource } from '@/types/extended_models';

const Wishlist = ({ products }: { products: BuyerProductResource[] }) => {
    return (
        <section>
            <div className="flex items-center justify-between mb-5">
                <h2 className="font-display text-2xl text-ink">
                    Mes coups de cœur
                </h2>
                <p className="text-xs text-ink-soft tracking-widest uppercase">
                    {products.length} {products.length > 1 ? 'articles' : 'article'}
                </p>
            </div>

            {products.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {products.map((product) => {
                        const cover = product.images[0]?.url;
                        const onSale = product.sale_price && Number(product.sale_price) < Number(product.base_price);
                        
                        return (
                            <article key={product.id} className="group relative flex flex-col border border-ink/15 bg-paper overflow-hidden">
                                <div className="flex p-4 gap-4">
                                    <Link href={`/products/${product.slug}`} className="relative h-24 w-24 shrink-0 overflow-hidden bg-ink/5 border border-ink/10">
                                        {cover ? (
                                            <img
                                                src={cover}
                                                alt={product.name}
                                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-[10px] text-ink-soft">
                                                Sans image
                                            </div>
                                        )}
                                        {onSale && (
                                            <span className="absolute top-1 left-1 bg-terracotta px-1.5 py-0.5 text-[8px] tracking-widest text-cream uppercase">
                                                Promo
                                            </span>
                                        )}
                                    </Link>

                                    <div className="flex flex-1 flex-col min-w-0">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-[10px] tracking-widest text-ink-soft uppercase truncate mb-0.5">
                                                    {product.category?.name}
                                                </p>
                                                <h3 className="font-display text-base leading-tight text-ink group-hover:text-terracotta transition-colors truncate">
                                                    <Link href={`/products/${product.slug}`}>
                                                        {product.name}
                                                    </Link>
                                                </h3>
                                            </div>
                                            <button
                                                onClick={() => {
                                                    if (confirm('Voulez-vous retirer cet article de vos coups de cœur ?')) {
                                                        import('@inertiajs/react').then(({ router }) => {
                                                            router.post(route('products.wishlist', product.id), {}, { preserveScroll: true });
                                                        });
                                                    }
                                                }}
                                                className="p-1 text-ink/30 hover:text-terracotta transition-colors"
                                                title="Retirer"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>

                                        <p className="mt-1 text-xs text-ink-soft italic truncate">
                                            par {product.store.store_name}
                                        </p>

                                        <div className="mt-auto pt-2 flex items-center justify-between">
                                            <div className="flex flex-col">
                                                <span className="font-display text-base text-ink leading-none">
                                                    {onSale ? Number(product.sale_price) : Number(product.base_price)} <span className="text-[10px]">MAD</span>
                                                </span>
                                            </div>
                                            
                                            <Link href={`/products/${product.slug}`}>
                                                <Button size="sm" variant="outline" className="h-8 rounded-none border-ink/20 hover:border-ink hover:bg-ink hover:text-cream text-xs px-3">
                                                    Détails
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center border border-dashed border-ink/15 bg-paper/50 py-16 text-center">
                    <div className="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-cream-deep/60 text-ink/20">
                        <Heart className="h-7 w-7" strokeWidth={1} />
                    </div>
                    <h3 className="font-display text-xl text-ink">Votre liste est vide</h3>
                    <p className="mt-2 max-w-xs text-sm text-ink-soft">
                        Enregistrez vos pièces favorites pour les retrouver ici.
                    </p>
                    <Link href="/products" className="mt-6">
                        <Button className="rounded-none bg-ink px-8 text-cream hover:bg-terracotta">
                            Explorer le marché
                        </Button>
                    </Link>
                </div>
            )}
        </section>
    );
};

Wishlist.layout = (page: React.ReactNode) => <BuyerLayout children={page} />;
export default Wishlist;
