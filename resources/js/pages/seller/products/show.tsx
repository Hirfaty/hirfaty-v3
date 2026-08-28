import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Package, AlertCircle, Send } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import SellerLayout from '@/layouts/seller/layout';
import type { Category, Media, Product, ProductStatus } from '@/types';

const PRODUCT_STATUS_LABEL: Record<ProductStatus, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    archived: 'Archivé',
    rejected: 'Rejeté',
    pending_review: 'En révision',
};

const PRODUCT_STATUS_CLASSNAME: Record<ProductStatus, string> = {
    draft: 'bg-slate-500/10 text-slate-700 border-slate-500/30',
    published: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30',
    archived: 'bg-slate-500/10 text-slate-700 border-slate-500/30',
    rejected: 'bg-rose-500/10 text-rose-700 border-rose-500/30',
    pending_review: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
};

function SellerProductPreview({
    product,
    stats,
}: {
    product: Product & { media: Media[]; category: Category };
    stats: {
        total_views: number;
        total_wishlists: number;
        total_orders: number;
    };
}) {
    const images = product.media.filter((media) => media.type === 'image');
    const videos = product.media.filter((media) => media.type === 'video');
    const heroMedia = images[0] ?? videos[0] ?? null;
    const onSale =
        product.sale_price !== null &&
        Number(product.sale_price) > 0 &&
        Number(product.sale_price) < Number(product.base_price);
    const discount = onSale
        ? Math.round(
              (1 - Number(product.sale_price) / Number(product.base_price)) *
                  100,
          )
        : 0;

    const formattedBasePrice = Number(product.base_price).toLocaleString('fr-FR');
    const formattedSalePrice = product.sale_price
        ? Number(product.sale_price).toLocaleString('fr-FR')
        : null;

    return (
        <div className="mx-auto max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8">
            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link
                        href={route('seller.products.index')}
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Retour au catalogue
                    </Link>
                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <span className="text-xs tracking-[0.2em] text-muted-foreground uppercase">
                            Aperçu produit
                        </span>
                        <Badge
                            variant="outline"
                            className={`rounded-full px-3 py-1 text-sm font-semibold ${PRODUCT_STATUS_CLASSNAME[product.status]}`}
                        >
                            {PRODUCT_STATUS_LABEL[product.status]}
                        </Badge>
                    </div>
                    <h1 className="mt-4 font-display text-4xl leading-tight md:text-5xl lg:text-6xl">
                        {product.name}
                    </h1>
                    <div className="mt-4 max-w-2xl prose prose-sm dark:prose-invert leading-relaxed text-muted-foreground">
                        {product.description ? (
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: product.description,
                                }}
                            />
                        ) : (
                            'Aucune description disponible pour ce produit.'
                        )}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    {product.status === 'draft' && (
                        <Button
                            variant="outline"
                            className="h-11 gap-2 rounded-xl"
                            onClick={() => {
                                router.patch(
                                    route(
                                        'seller.products.submit',
                                        product.id,
                                    ),
                                    undefined,
                                    {
                                        onSuccess: () =>
                                            toast.success(
                                                'Produit soumis pour révision',
                                            ),
                                    },
                                );
                            }}
                        >
                            <Send className="h-4 w-4" />
                            Soumettre pour révision
                        </Button>
                    )}
                    <Button asChild variant="outline" className="h-11 gap-2 rounded-xl">
                        <Link href={route('seller.products.edit', product.id)}>
                            <Pencil className="h-4 w-4" />
                            Modifier
                        </Link>
                    </Button>
                </div>
            </div>

            {product.status === 'draft' && (
                <div className="mb-8 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 text-sm text-amber-800">
                    <div className="mb-3 flex items-center gap-2">
                        <AlertCircle className="h-4 w-4" />
                        <span className="font-semibold">Brouillon</span>
                    </div>
                    <p>
                        Ce produit n'est pas encore visible sur le marché.
                        Soumettez-le pour révision une fois qu'il est prêt.
                    </p>
                </div>
            )}

            {product.status === 'rejected' && (
                <div className="mb-8 rounded-2xl border border-destructive/20 bg-destructive/5 p-6 text-sm text-destructive">
                    <div className="mb-3 flex items-center gap-2">
                        <AlertCircle className="h-4 w-4" />
                        <span className="font-semibold">Produit rejeté</span>
                    </div>
                    <p>
                        {product.rejection_reason ||
                            'Ce produit a été rejeté. Veuillez corriger les informations et le soumettre de nouveau.'}
                    </p>
                </div>
            )}

            <div className="grid gap-10 lg:grid-cols-[1fr_320px]">
                <div className="space-y-6">
                    <Card className="rounded-2xl border border-border bg-card/50">
                        <CardContent className="p-0">
                            <div className="relative aspect-[4/5] overflow-hidden rounded-t-2xl bg-muted">
                                {heroMedia ? (
                                    heroMedia.type === 'video' ? (
                                        <video
                                            src={heroMedia.url}
                                            controls
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <img
                                            src={heroMedia.url}
                                            alt={product.name}
                                            className="h-full w-full object-cover"
                                        />
                                    )
                                ) : (
                                    <div className="flex h-full items-center justify-center text-muted-foreground">
                                        <Package className="h-10 w-10" />
                                    </div>
                                )}
                            </div>

                            <div className="grid grid-cols-4 gap-3 p-4">
                                {product.media.length > 0 ? (
                                    product.media.slice(0, 4).map((media) => (
                                        <div
                                            key={media.id}
                                            className="aspect-square overflow-hidden rounded-2xl border border-border bg-muted"
                                        >
                                            {media.type === 'video' ? (
                                                <video
                                                    src={media.url}
                                                    muted
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <img
                                                    src={media.url}
                                                    alt={product.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <div className="col-span-4 flex h-24 items-center justify-center rounded-2xl border border-dashed border-border text-sm text-muted-foreground">
                                        Aucun média disponible
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-2xl border border-border bg-card/50 p-6">
                        <CardHeader className="p-0">
                            <CardTitle className="font-display text-xl">
                                Détails du produit
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6 pt-6">
                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Catégorie
                                    </p>
                                    <p className="mt-2 text-sm text-foreground">
                                        {product.category?.name || 'Sans catégorie'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Stock
                                    </p>
                                    <p className="mt-2 text-sm font-medium">
                                        {product.stock > 0 ? (
                                            `${product.stock} en stock`
                                        ) : (
                                            <span className="text-destructive">Rupture de stock</span>
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Prix final
                                    </p>
                                    <div className="mt-2 flex items-center gap-3">
                                        <span className="font-display text-3xl tabular-nums">
                                            {formattedSalePrice || formattedBasePrice}{' '}
                                            <span className="text-base text-muted-foreground">
                                                MAD
                                            </span>
                                        </span>
                                        {onSale && (
                                            <Badge className="rounded-full border border-destructive/30 bg-destructive/10 text-destructive">
                                                -{discount}%
                                            </Badge>
                                        )}
                                    </div>
                                    {onSale && (
                                        <p className="mt-1 text-sm text-muted-foreground line-through">
                                            {formattedBasePrice} MAD
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                        Statut produit
                                    </p>
                                    <p className="mt-2 text-sm text-foreground">
                                        {PRODUCT_STATUS_LABEL[product.status]}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Description
                                </p>
                                <div className="mt-3 prose prose-sm dark:prose-invert max-w-none leading-relaxed text-muted-foreground">
                                    {product.description ? (
                                        <div
                                            dangerouslySetInnerHTML={{
                                                __html: product.description,
                                            }}
                                        />
                                    ) : (
                                        'Aucune description renseignée.'
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <aside className="space-y-6">
                    <Card className="rounded-2xl border border-border bg-card/50 p-6">
                        <CardHeader className="p-0">
                            <CardTitle className="font-display text-xl">Statistiques rapides</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-6">
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>Vues totales</span>
                                <span className="font-semibold tabular-nums">{stats.total_views}</span>
                            </div>
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>Ajouté aux favoris</span>
                                <span className="font-semibold tabular-nums">{stats.total_wishlists}</span>
                            </div>
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>Quantité vendue</span>
                                <span className="font-semibold tabular-nums">{stats.total_orders}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-2xl border border-border bg-card/50 p-6">
                        <CardHeader className="p-0">
                            <CardTitle className="font-display text-xl">Aperçu rapide</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-6 text-sm text-muted-foreground">
                            <div className="flex items-center justify-between">
                                <span>ID du produit</span>
                                <span className="font-mono text-xs">#{product.id}</span>
                            </div>
                            <Separator />
                            <div className="flex items-center justify-between">
                                <span>Prix de base</span>
                                <span className="tabular-nums">{formattedBasePrice} MAD</span>
                            </div>
                            <Separator />
                            <div className="flex items-center justify-between">
                                <span>Stock disponible</span>
                                <span className="tabular-nums">{product.stock}</span>
                            </div>
                            <Separator />
                            <div className="flex items-center justify-between">
                                <span>Catégorie</span>
                                <span className="text-right">{product.category?.name || '—'}</span>
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </div>
    );
}

SellerProductPreview.layout = (page: React.ReactNode) => (
    <SellerLayout children={page} />
);

export default SellerProductPreview;
