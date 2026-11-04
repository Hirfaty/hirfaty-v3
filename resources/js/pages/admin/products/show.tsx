import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    CircleX,
    Pencil,
    PlayCircle,
    Star,
    TriangleAlert,
    SquareArrowOutUpRight,
    Trash2,
    X,
    ZoomIn,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ProductDescription from '@/components/product-description';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import AdminLayout from '@/layouts/admin/layout';
import { frenchName } from '@/lib/category';
import { formatDate } from '@/lib/utils';
import type {
    Category,
    Media,
    Order,
    OrderItem,
    PageProps,
    Product,
    ProductStatus,
    ProductVariant,
    Store,
    User,
} from '@/types';

const STATUS_BADGE: Record<ProductStatus, string> = {
    draft: 'bg-slate-100 text-slate-700',
    pending_review: 'bg-amber-100 text-amber-700',
    published: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    archived: 'bg-slate-100 text-slate-700',
};

const STATUS_LABEL: Record<ProductStatus, string> = {
    draft: 'Brouillon',
    pending_review: 'En attente',
    published: 'Publié',
    rejected: 'Rejeté',
    archived: 'Archivé',
};

const REJECTION_REASONS = [
    'Photos de mauvaise qualité',
    'Description incomplète ou trompeuse',
    'Prix non conforme aux standards',
    'Produit non artisanal',
    'Catégorie incorrecte',
    'Contenu interdit ou inapproprié',
    "Doublon d'une annonce existante",
    'Autre',
] as const;

interface AdminReview {
    id: number;
    rating: number;
    comment: string | null;
    created_at: string | null;
    /** Unmasked — this page is for Hirfaty's desk only. */
    user: {
        id: number;
        first_name: string;
        last_name: string;
        email: string | null;
    } | null;
    order_reference: string | null;
}

interface AdminProductShowProps {
    reviews: AdminReview[];
    /** Resolved server-side by ProductShippingService. */
    shipping: {
        complete: boolean;
        /** The product's own code, else its category's. */
        hs_code: string | null;
        hs_code_inherited: boolean;
    };
    product: Product & {
        /** Null when the owner's account no longer exists. */
        store: Store & { user: User | null };
        category: Category;
        media: Media[];
        // The controller always eager-loads orderItems.order for this page,
        // so `order` is guaranteed here even though it's optional on the
        // base OrderItem type.
        order_items: (OrderItem & { order: Order })[];
        variants: ProductVariant[];
    };
}

/** Small bordered card, the base unit this whole page is built from. */
function Card({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="border-b border-border px-5 py-4">
                <h2 className="text-sm font-semibold text-ink">{title}</h2>
                {subtitle && (
                    <p className="mt-0.5 text-xs text-ink-soft">{subtitle}</p>
                )}
            </div>
            {children}
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <p className="text-xs font-medium text-ink-soft">{label}</p>
            <div className="mt-1 text-sm text-ink">{children}</div>
        </div>
    );
}

/** "40 × 30 × 12 cm", or null unless all three sides are known. */
function formatDimensions(
    length: string | null | undefined,
    width: string | null | undefined,
    height: string | null | undefined,
    unit: string,
): string | null {
    if (!length || !width || !height) {
        return null;
    }

    return `${Number(length)} × ${Number(width)} × ${Number(height)} ${unit}`;
}

function AdminProductShow({
    product,
    reviews,
    shipping,
}: AdminProductShowProps) {
    const [mediaOpen, setMediaOpen] = useState(false);
    const [mediaIndex, setMediaIndex] = useState(0);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const { data, setData, patch, processing } = useForm<{
        rejection_reason: string;
    }>({
        rejection_reason: REJECTION_REASONS[0],
    });

    const averageRating =
        reviews.length > 0
            ? reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length
            : null;

    const images = product.media.filter((m) => m.type === 'image');
    const videos = product.media.filter((m) => m.type === 'video');

    const approve = () => {
        router.patch(route('admin.products.approve', product.id));
    };

    const reject = () => {
        patch(route('admin.products.reject', product.id));
    };

    const archive = () => {
        router.patch(route('admin.products.archive', product.id));
    };

    // A product that has ever been ordered can't be hard-deleted — the
    // controller enforces this too, but checking it here avoids offering an
    // action that's guaranteed to fail.
    const hasOrders = product.order_items.length > 0;

    const confirmDelete = () => {
        setDeleting(true);
        router.delete(route('admin.products.destroy', product.id), {
            // A successful *request* isn't the same as a successful
            // *deletion* — the destroy endpoint replies 200 either way and
            // reports the real outcome via `flash`, so that's what has to
            // be read here. On an actual delete this page redirects to the
            // index, so `page.props` is that page's props by the time this
            // runs.
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);
                } else {
                    toast.success(flash?.success ?? 'Produit supprimé.');
                }
            },
            onFinish: () => setDeleting(false),
        });
    };

    const totalSales = product.order_items.reduce(
        (sum, item) => sum + item.quantity,
        0,
    );

    const totalRevenue = product.order_items.reduce(
        (sum, item) => sum + parseFloat(item.unit_price) * item.quantity,
        0,
    );

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={product.name} />
            {/* ── Header ───────────────────────────────────── */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                    <Link
                        href={route('admin.products.index')}
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-ink-soft transition hover:bg-muted hover:text-ink"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-ink">
                            {product.name}
                        </h1>
                        <p className="text-sm text-ink-soft">
                            #{product.id} · Soumis le{' '}
                            {formatDate(product.created_at)}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2.5">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[product.status]}`}
                    >
                        {STATUS_LABEL[product.status]}
                    </span>
                    <Link
                        href={route('admin.products.edit', product.id)}
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-ink-soft transition hover:bg-muted hover:text-ink"
                        title="Modifier"
                    >
                        <Pencil className="h-4 w-4" />
                    </Link>
                    {/*
                     * The admin preview, not the public URL: that one 404s
                     * until the product is published, priced and its shop
                     * open — exactly when the desk most needs to look.
                     */}
                    <a
                        href={route('admin.products.preview', product.id)}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm text-ink-soft transition hover:bg-muted hover:text-ink"
                        title="Voir la fiche comme un acheteur"
                    >
                        <SquareArrowOutUpRight className="h-4 w-4" />
                        Aperçu
                    </a>
                    <button
                        onClick={() => setDeleteOpen(true)}
                        disabled={hasOrders}
                        title={
                            hasOrders
                                ? 'Ce produit a des commandes — archivez-le plutôt'
                                : 'Supprimer'
                        }
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-ink-soft transition hover:border-terracotta hover:bg-terracotta hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-border disabled:hover:bg-transparent disabled:hover:text-ink-soft"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* ── Main column ──────────────────────────── */}
                <div className="space-y-6 lg:col-span-2">
                    <Card
                        title="Galerie"
                        subtitle={`${images.length} image${images.length > 1 ? 's' : ''} · ${videos.length} vidéo${videos.length > 1 ? 's' : ''}`}
                    >
                        {images.length === 0 && videos.length === 0 ? (
                            <p className="p-5 text-sm text-ink-soft">
                                Aucun média ajouté.
                            </p>
                        ) : (
                            <>
                                {images.length > 0 && (
                                    <div className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 md:grid-cols-4">
                                        {images.map((media, index) => (
                                            <button
                                                key={media.id}
                                                type="button"
                                                onClick={() => {
                                                    setMediaIndex(index);
                                                    setMediaOpen(true);
                                                }}
                                                className="group relative aspect-square overflow-hidden rounded-lg border border-border bg-muted"
                                            >
                                                <img
                                                    src={media.url}
                                                    alt=""
                                                    className="h-full w-full object-cover transition group-hover:scale-105"
                                                />
                                                <div className="absolute inset-0 flex items-center justify-center bg-ink/0 opacity-0 transition group-hover:bg-ink/20 group-hover:opacity-100">
                                                    <ZoomIn className="h-5 w-5 text-white" />
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {videos.length > 0 && (
                                    <div className="grid grid-cols-1 gap-3 border-t border-border p-5 sm:grid-cols-2">
                                        {videos.map((media, index) => (
                                            <button
                                                key={media.id}
                                                type="button"
                                                onClick={() => {
                                                    setMediaIndex(
                                                        images.length + index,
                                                    );
                                                    setMediaOpen(true);
                                                }}
                                                className="group relative aspect-video overflow-hidden rounded-lg border border-border bg-muted"
                                            >
                                                <video
                                                    src={media.url}
                                                    className="h-full w-full object-cover"
                                                    muted
                                                    onMouseEnter={(e) =>
                                                        e.currentTarget.play()
                                                    }
                                                    onMouseLeave={(e) =>
                                                        e.currentTarget.pause()
                                                    }
                                                />
                                                <div className="absolute inset-0 flex items-center justify-center bg-ink/20">
                                                    <PlayCircle className="h-8 w-8 text-white" />
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </Card>

                    <Card title="Détails du produit">
                        <div className="space-y-5 p-5">
                            <Field label="Description">
                                {product.description ? (
                                    <ProductDescription
                                        html={product.description}
                                        className="leading-relaxed"
                                    />
                                ) : (
                                    <p className="text-ink-soft">
                                        Aucune description fournie.
                                    </p>
                                )}
                            </Field>

                            <div className="grid grid-cols-2 gap-5 sm:grid-cols-3">
                                <Field label="Référence (SKU)">
                                    <span className="font-mono">
                                        {product.sku ?? '—'}
                                    </span>
                                </Field>
                                <Field label="Catégorie">
                                    {product.category
                                        ? frenchName(product.category)
                                        : 'Non catégorisé'}
                                </Field>
                                <Field label="Stock">
                                    {product.has_variants ? (
                                        <>
                                            {product.variants.reduce(
                                                (sum, v) => sum + v.stock,
                                                0,
                                            )}{' '}
                                            unités{' '}
                                            <span className="text-xs text-ink-soft">
                                                (réparties sur{' '}
                                                {product.variants.length}{' '}
                                                déclinaison
                                                {product.variants.length > 1
                                                    ? 's'
                                                    : ''}
                                                )
                                            </span>
                                        </>
                                    ) : (
                                        `${product.stock} unités`
                                    )}
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Prix de base">
                                    {product.base_price === null ? (
                                        // Not priced yet — kept out of the
                                        // storefront until a figure is set.
                                        <span className="text-xl font-semibold text-amber-700">
                                            À définir
                                        </span>
                                    ) : (
                                        <span className="text-xl font-semibold text-ink">
                                            {product.base_price} MAD
                                        </span>
                                    )}
                                </Field>
                                {product.sale_price && (
                                    <Field label="Prix promo">
                                        <span className="text-xl font-semibold text-terracotta">
                                            {product.sale_price} MAD
                                        </span>
                                    </Field>
                                )}
                            </div>

                            {/*
                             * Professional pricing is optional per product —
                             * a product sold only at retail simply has no
                             * professional_price, and there is nothing useful
                             * to show an admin in that case.
                             */}
                            {(product.professional_price ||
                                product.professional_sale_price) && (
                                <div className="grid grid-cols-2 gap-5 border-t border-border pt-5">
                                    <Field label="Prix professionnel">
                                        {product.professional_price ? (
                                            <span className="text-xl font-semibold text-olive">
                                                {product.professional_price} MAD
                                            </span>
                                        ) : (
                                            <span className="text-sm text-ink-soft">
                                                Non défini — facturé au prix de
                                                base
                                            </span>
                                        )}
                                    </Field>
                                    {product.professional_sale_price && (
                                        <Field label="Prix promo professionnel">
                                            <span className="text-xl font-semibold text-terracotta">
                                                {
                                                    product.professional_sale_price
                                                }{' '}
                                                MAD
                                            </span>
                                        </Field>
                                    )}
                                    <Field label="Commande minimum (pro)">
                                        {product.min_order_qty_professional}{' '}
                                        unités
                                    </Field>
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card
                        title="Expédition"
                        subtitle="Depuis l’entrepôt Hirfaty — Maroc (MA)"
                    >
                        <div className="space-y-5 p-5">
                            {!shipping.complete && (
                                <p className="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                                    <TriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                                    Poids ou dimensions manquants
                                    {product.has_variants
                                        ? ' pour au moins une déclinaison'
                                        : ''}{' '}
                                    — le transporteur ne pourra pas calculer ce
                                    colis.
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-5">
                                <Field label="Poids">
                                    {product.weight
                                        ? `${Number(product.weight)} ${product.weight_unit}`
                                        : '—'}
                                </Field>
                                <Field label="Dimensions du colis">
                                    {formatDimensions(
                                        product.length,
                                        product.width,
                                        product.height,
                                        product.dimension_unit ?? 'cm',
                                    ) ?? '—'}
                                </Field>
                                <Field label="Code SH">
                                    {shipping.hs_code ? (
                                        <>
                                            {shipping.hs_code}
                                            {shipping.hs_code_inherited && (
                                                <span className="ms-1.5 text-xs text-ink-soft">
                                                    (catégorie)
                                                </span>
                                            )}
                                        </>
                                    ) : (
                                        <span className="text-amber-700">
                                            Non défini
                                        </span>
                                    )}
                                </Field>
                                <Field label="Délai de préparation">
                                    {product.handling_days === null ||
                                    product.handling_days === undefined
                                        ? '—'
                                        : `${product.handling_days} jour${product.handling_days > 1 ? 's' : ''}`}
                                </Field>
                                <Field label="Description douanière">
                                    {product.customs_description || '—'}
                                </Field>
                                <Field label="Fragile">
                                    {product.is_fragile ? 'Oui' : 'Non'}
                                </Field>
                            </div>

                            {product.variants.some(
                                (v) =>
                                    v.weight || v.length || v.width || v.height,
                            ) && (
                                <div className="border-t border-border pt-4">
                                    <p className="text-xs font-medium text-ink-soft">
                                        Valeurs propres aux déclinaisons
                                    </p>
                                    <ul className="mt-2 space-y-1 text-sm text-ink">
                                        {product.variants
                                            .filter(
                                                (v) =>
                                                    v.weight ||
                                                    v.length ||
                                                    v.width ||
                                                    v.height,
                                            )
                                            .map((v) => (
                                                <li key={v.id}>
                                                    <span className="font-medium">
                                                        {v.options
                                                            .map((o) => o.value)
                                                            .join(' / ')}
                                                    </span>
                                                    {' — '}
                                                    {[
                                                        v.weight &&
                                                            `${Number(v.weight)} ${product.weight_unit}`,
                                                        (v.length ||
                                                            v.width ||
                                                            v.height) &&
                                                            `${[v.length ?? product.length, v.width ?? product.width, v.height ?? product.height].map((d) => (d ? Number(d) : '?')).join(' × ')} ${product.dimension_unit}`,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ')}
                                                </li>
                                            ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    </Card>

                    {product.variants.length > 0 && (
                        <Card
                            title="Déclinaisons"
                            subtitle={`${product.variants.length} déclinaison${product.variants.length > 1 ? 's' : ''}`}
                        >
                            <div className="divide-y divide-border">
                                {product.variants.map((variant) => (
                                    <div
                                        key={variant.id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                                    >
                                        <div className="flex flex-wrap items-center gap-2.5">
                                            {(variant.media?.length ?? 0) >
                                                0 && (
                                                <div className="flex -space-x-2">
                                                    {variant.media!.map((m) => (
                                                        <img
                                                            key={m.id}
                                                            src={m.url}
                                                            alt=""
                                                            className="h-8 w-8 shrink-0 rounded-full border-2 border-card object-cover"
                                                        />
                                                    ))}
                                                </div>
                                            )}
                                            {variant.options.map((option) => (
                                                <span
                                                    key={option.id}
                                                    className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 px-2.5 py-1 text-xs font-medium text-ink"
                                                >
                                                    <span className="text-ink-soft">
                                                        {option.name}
                                                    </span>
                                                    {option.value}
                                                </span>
                                            ))}
                                            {variant.sku && (
                                                <span className="text-xs text-ink-soft">
                                                    SKU: {variant.sku}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4 text-sm">
                                            <span className="text-ink-soft">
                                                {variant.stock} unités
                                            </span>
                                            <span className="font-medium text-ink">
                                                {variant.price_override
                                                    ? `${variant.price_override} MAD`
                                                    : 'Prix hérité'}
                                            </span>
                                            {variant.professional_price_override && (
                                                <span className="font-medium text-olive">
                                                    {
                                                        variant.professional_price_override
                                                    }{' '}
                                                    MAD (pro)
                                                </span>
                                            )}
                                            {!variant.is_active && (
                                                <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                    Inactive
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}

                    {product.order_items.length > 0 && (
                        <Card
                            title="Historique des commandes"
                            subtitle={`${product.order_items.length} commande${product.order_items.length > 1 ? 's' : ''} · ${totalSales} unité${totalSales > 1 ? 's' : ''} vendue${totalSales > 1 ? 's' : ''} · ${totalRevenue.toFixed(2)} MAD de revenus`}
                        >
                            <div className="divide-y divide-border">
                                {product.order_items
                                    .slice(0, 10)
                                    .map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between px-5 py-4"
                                        >
                                            <div>
                                                <p className="text-sm font-medium text-ink">
                                                    Commande #{item.order.id}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    {formatDate(
                                                        item.order.created_at,
                                                    )}{' '}
                                                    · {item.quantity} unités
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium text-ink">
                                                    {(
                                                        parseFloat(
                                                            item.unit_price,
                                                        ) * item.quantity
                                                    ).toFixed(2)}{' '}
                                                    MAD
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    {item.unit_price} MAD /
                                                    unité
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </Card>
                    )}

                    {reviews.length > 0 && (
                        <Card
                            title="Avis des acheteurs"
                            subtitle={`${reviews.length} avis · note moyenne ${averageRating?.toFixed(1)} / 5`}
                        >
                            <div className="divide-y divide-border">
                                {reviews.map((review) => (
                                    <div
                                        key={review.id}
                                        className="space-y-2 px-5 py-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="inline-flex items-center gap-0.5"
                                                    role="img"
                                                    aria-label={`${review.rating} sur 5`}
                                                >
                                                    {[1, 2, 3, 4, 5].map(
                                                        (n) => (
                                                            <Star
                                                                key={n}
                                                                className={`h-4 w-4 ${
                                                                    n <=
                                                                    review.rating
                                                                        ? 'fill-amber-400 text-amber-400'
                                                                        : 'fill-transparent text-ink-soft/30'
                                                                }`}
                                                            />
                                                        ),
                                                    )}
                                                </span>
                                                <span className="text-sm font-semibold text-ink">
                                                    {review.rating}/5
                                                </span>
                                            </div>
                                            <p className="text-xs text-ink-soft">
                                                {formatDate(review.created_at)}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                            {review.user ? (
                                                <Link
                                                    href={route(
                                                        'admin.users.show',
                                                        review.user.id,
                                                    )}
                                                    className="font-medium text-ink hover:underline"
                                                >
                                                    {review.user.first_name}{' '}
                                                    {review.user.last_name}
                                                </Link>
                                            ) : (
                                                <span className="text-ink-soft">
                                                    Compte supprimé
                                                </span>
                                            )}
                                            {review.user?.email && (
                                                <span className="text-xs text-ink-soft">
                                                    {review.user.email}
                                                </span>
                                            )}
                                            {review.order_reference && (
                                                <Link
                                                    href={route(
                                                        'admin.orders.show',
                                                        review.order_reference,
                                                    )}
                                                    className="text-xs text-ink-soft hover:text-ink hover:underline"
                                                >
                                                    Commande{' '}
                                                    {review.order_reference}
                                                </Link>
                                            )}
                                        </div>

                                        {review.comment ? (
                                            <p className="text-sm leading-relaxed whitespace-pre-line text-ink">
                                                {review.comment}
                                            </p>
                                        ) : (
                                            <p className="text-xs text-ink-soft italic">
                                                Aucun commentaire.
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>

                {/* ── Sidebar ──────────────────────────────── */}
                <div className="space-y-6">
                    <Card title="Boutique">
                        <div className="space-y-4 p-5">
                            <Field label="Nom">
                                {product.store.store_name}
                            </Field>
                            <Field label="Propriétaire">
                                {product.store.user ? (
                                    <>
                                        <p>
                                            {product.store.user.first_name}{' '}
                                            {product.store.user.last_name}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {product.store.user.email}
                                        </p>
                                    </>
                                ) : (
                                    <span className="text-ink-soft">
                                        Compte supprimé
                                    </span>
                                )}
                            </Field>
                            <Field label="Ville">
                                {product.store.city?.name || 'Non renseignée'}
                            </Field>
                        </div>
                    </Card>

                    {(product.status === 'rejected' || product.reviewed_at) && (
                        <Card title="Modération">
                            <div className="space-y-4 p-5">
                                {product.reviewed_at && (
                                    <Field label="Examiné le">
                                        {formatDate(product.reviewed_at)}
                                    </Field>
                                )}
                                {product.rejection_reason && (
                                    <Field label="Motif de rejet">
                                        {product.rejection_reason}
                                    </Field>
                                )}
                            </div>
                        </Card>
                    )}

                    <Card title="Actions">
                        <div className="space-y-3 p-5">
                            {product.status === 'pending_review' && (
                                <>
                                    <button
                                        onClick={approve}
                                        disabled={processing}
                                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-ink text-sm font-semibold text-white transition hover:bg-ink/90 disabled:opacity-50"
                                    >
                                        <CircleCheck className="h-4 w-4" />
                                        Approuver
                                    </button>
                                    <button
                                        onClick={reject}
                                        disabled={processing}
                                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border text-sm font-semibold text-ink transition hover:bg-muted disabled:opacity-50"
                                    >
                                        <CircleX className="h-4 w-4" />
                                        Rejeter
                                    </button>

                                    <div className="border-t border-border pt-3">
                                        <label className="text-xs font-medium text-ink-soft">
                                            Motif de rejet
                                        </label>
                                        <select
                                            value={data.rejection_reason}
                                            onChange={(e) =>
                                                setData(
                                                    'rejection_reason',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-2 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                                        >
                                            {REJECTION_REASONS.map((reason) => (
                                                <option
                                                    key={reason}
                                                    value={reason}
                                                >
                                                    {reason}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </>
                            )}

                            {product.status === 'published' && (
                                <button
                                    onClick={archive}
                                    disabled={processing}
                                    className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border text-sm font-semibold text-ink transition hover:bg-muted disabled:opacity-50"
                                >
                                    Archiver
                                </button>
                            )}

                            {product.status === 'draft' && (
                                <div className="rounded-lg bg-muted/60 p-3 text-sm text-ink-soft">
                                    Ce produit est un brouillon. Complétez-le
                                    depuis la fiche d'édition pour le publier.
                                </div>
                            )}

                            {(product.status === 'archived' ||
                                product.status === 'rejected') && (
                                <p className="text-sm text-ink-soft">
                                    Aucune action disponible pour un produit{' '}
                                    {STATUS_LABEL[product.status].toLowerCase()}
                                    .
                                </p>
                            )}

                            <Link
                                href={route('admin.products.edit', product.id)}
                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border text-sm font-medium text-ink transition hover:bg-muted"
                            >
                                <Pencil className="h-4 w-4" />
                                Modifier le produit
                            </Link>

                            <button
                                onClick={() => setDeleteOpen(true)}
                                disabled={hasOrders}
                                title={
                                    hasOrders
                                        ? 'Ce produit a des commandes — archivez-le plutôt'
                                        : undefined
                                }
                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-terracotta/30 text-sm font-medium text-terracotta transition hover:bg-terracotta hover:text-white disabled:cursor-not-allowed disabled:border-border disabled:text-ink-soft/50 disabled:hover:bg-transparent"
                            >
                                <Trash2 className="h-4 w-4" />
                                Supprimer le produit
                            </button>
                            {hasOrders && (
                                <p className="text-center text-xs text-ink-soft">
                                    Ce produit a des commandes et ne peut pas
                                    être supprimé.
                                </p>
                            )}
                        </div>
                    </Card>
                </div>
            </div>

            {/* ── Media lightbox ──────────────────────────── */}
            <Dialog open={mediaOpen} onOpenChange={setMediaOpen}>
                <DialogContent className="max-w-4xl">
                    <DialogTitle className="sr-only">
                        {product.name} — média {mediaIndex + 1}
                    </DialogTitle>
                    {product.media[mediaIndex] && (
                        <div className="relative">
                            {product.media[mediaIndex].type === 'image' ? (
                                <img
                                    src={product.media[mediaIndex].url}
                                    alt=""
                                    className="h-auto w-full rounded-lg"
                                />
                            ) : (
                                <video
                                    src={product.media[mediaIndex].url}
                                    controls
                                    className="h-auto w-full rounded-lg"
                                />
                            )}
                            <button
                                onClick={() => setMediaOpen(false)}
                                className="absolute top-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-black/80"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* ── Delete confirmation ─────────────────────────── */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer «&nbsp;{product.name}&nbsp;» ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible : le produit, ses
                            photos et ses déclinaisons seront supprimés
                            définitivement.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            disabled={deleting}
                            className="bg-terracotta text-white hover:bg-terracotta/90"
                        >
                            {deleting ? 'Suppression…' : 'Supprimer'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

AdminProductShow.layout = (page: ReactNode) => <AdminLayout children={page} />;

export default AdminProductShow;
