import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    CircleCheck,
    CircleX,
    Eye,
    Images,
    Package,
    PlayCircle,
    SquareArrowOutUpRight,
    X,
    ZoomIn,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type {
    Category,
    Media,
    Order,
    OrderItem,
    Product,
    ProductStatus,
    Store,
    User,
} from '@/types';

const statusPill: Record<ProductStatus, string> = {
    pending_review: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    archived: 'bg-slate-100 text-slate-800',
};

const statusLabel: Record<ProductStatus, string> = {
    pending_review: 'Pending Review',
    published: 'Published',
    rejected: 'Rejected',
    archived: 'Archived',
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

interface AdminProductShowProps {
    product: Product & {
        store: Store & { user: User };
        category: Category;
        media: Media[];
        order_items: (OrderItem & Order)[];
    };
}

function AdminProductShow({ product }: AdminProductShowProps) {
    console.log(product);
    const [mediaOpen, setMediaOpen] = useState(false);
    const [mediaIndex, setMediaIndex] = useState(0);

    const { data, setData, patch, processing } = useForm<{
        rejection_reason: string;
    }>({
        rejection_reason: REJECTION_REASONS[0],
    });

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

    const totalSales = product.order_items.reduce(
        (sum, item) => sum + item.quantity,
        0,
    );

    const totalRevenue = product.order_items.reduce(
        (sum, item) => sum + parseFloat(item.unit_price) * item.quantity,
        0,
    );

    return (
        <div className="mx-auto max-w-[1200px] p-6 md:p-8">
            {/* Header */}
            <div className="mb-8 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Link
                        href="/admin/products"
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            {product.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Product #{product.id} • Submitted{' '}
                            {formatDate(product.created_at)}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <Badge className={statusPill[product.status]}>
                        {statusLabel[product.status]}
                    </Badge>
                    <Link
                        href={`/products/${product.slug}`}
                        target="_blank"
                        className="flex h-10 w-10 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        title="View on storefront"
                    >
                        <SquareArrowOutUpRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                {/* Main Content */}
                <div className="space-y-8 lg:col-span-2">
                    {/* Media Gallery */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                Media Gallery
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {images.length} images • {videos.length} videos
                            </p>
                        </div>

                        {images.length > 0 && (
                            <div className="p-6">
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                                    {images.map((media, index) => (
                                        <div
                                            key={media.id}
                                            className="group relative aspect-square cursor-pointer overflow-hidden rounded-lg border border-border bg-muted"
                                            onClick={() => {
                                                setMediaIndex(index);
                                                setMediaOpen(true);
                                            }}
                                        >
                                            <img
                                                src={media.url}
                                                alt={`Product image ${index + 1}`}
                                                className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                            />
                                            <div className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20" />
                                            <div className="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                                                <div className="flex gap-1">
                                                    <button className="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-foreground backdrop-blur">
                                                        <ZoomIn className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {videos.length > 0 && (
                            <div className="border-t border-border p-6">
                                <h3 className="mb-4 text-sm font-medium text-foreground">
                                    Videos
                                </h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {videos.map((media, index) => (
                                        <div
                                            key={media.id}
                                            className="group relative aspect-video cursor-pointer overflow-hidden rounded-lg border border-border bg-muted"
                                            onClick={() => {
                                                setMediaIndex(
                                                    images.length + index,
                                                );
                                                setMediaOpen(true);
                                            }}
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
                                            <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-foreground">
                                                    <PlayCircle className="h-6 w-6" />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Product Details */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                Product Details
                            </h2>
                        </div>
                        <div className="space-y-6 p-6">
                            <div>
                                <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                    Description
                                </h3>
                                <p className="mt-2 leading-relaxed text-foreground">
                                    {product.description ||
                                        'No description provided.'}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                        Category
                                    </h3>
                                    <p className="mt-2 text-foreground">
                                        {product.category?.name ||
                                            'Uncategorized'}
                                    </p>
                                </div>
                                <div>
                                    <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                        Stock
                                    </h3>
                                    <p className="mt-2 text-foreground">
                                        {product.stock} units
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                        Base Price
                                    </h3>
                                    <p className="mt-2 text-2xl font-bold text-foreground">
                                        {product.base_price} MAD
                                    </p>
                                </div>
                                {product.sale_price && (
                                    <div>
                                        <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                            Sale Price
                                        </h3>
                                        <p className="mt-2 text-2xl font-bold text-terracotta">
                                            {product.sale_price} MAD
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Order History */}
                    {product.order_items.length > 0 && (
                        <div className="rounded-lg border border-border bg-card">
                            <div className="border-b border-border p-6">
                                <h2 className="text-lg font-semibold text-foreground">
                                    Order History
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {product.order_items.length} orders •{' '}
                                    {totalSales} units sold •{' '}
                                    {totalRevenue.toFixed(2)} MAD revenue
                                </p>
                            </div>
                            <div className="divide-y divide-border">
                                {product.order_items
                                    .slice(0, 10)
                                    .map((item) => (
                                        <div key={item.id} className="p-6">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="font-medium text-foreground">
                                                        Order #{item.order.id}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {formatDate(
                                                            item.order
                                                                .created_at,
                                                        )}{' '}
                                                        • {item.quantity} units
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium text-foreground">
                                                        {(
                                                            parseFloat(
                                                                item.unit_price,
                                                            ) * item.quantity
                                                        ).toFixed(2)}{' '}
                                                        MAD
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.unit_price} MAD
                                                        each
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Store Information */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                Store Information
                            </h2>
                        </div>
                        <div className="space-y-4 p-6">
                            <div>
                                <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                    Store Name
                                </h3>
                                <p className="mt-1 text-foreground">
                                    {product.store.store_name}
                                </p>
                            </div>
                            <div>
                                <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                    Owner
                                </h3>
                                <p className="mt-1 text-foreground">
                                    {product.store.user.first_name}{' '}
                                    {product.store.user.last_name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {product.store.user.email}
                                </p>
                            </div>
                            <div>
                                <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                    City
                                </h3>
                                <p className="mt-1 text-foreground">
                                    {product.store.city?.name ||
                                        'Not specified'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Review Information */}
                    {(product.status === 'rejected' || product.reviewed_at) && (
                        <div className="rounded-lg border border-border bg-card">
                            <div className="border-b border-border p-6">
                                <h2 className="text-lg font-semibold text-foreground">
                                    Review Information
                                </h2>
                            </div>
                            <div className="space-y-4 p-6">
                                {product.reviewed_at && (
                                    <div>
                                        <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                            Reviewed At
                                        </h3>
                                        <p className="mt-1 text-foreground">
                                            {formatDate(product.reviewed_at)}
                                        </p>
                                    </div>
                                )}
                                {product.rejection_reason && (
                                    <div>
                                        <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                            Rejection Reason
                                        </h3>
                                        <p className="mt-1 text-foreground">
                                            {product.rejection_reason}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="border-b border-border p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                Actions
                            </h2>
                        </div>
                        <div className="space-y-3 p-6">
                            {product.status === 'pending_review' && (
                                <>
                                    <Button
                                        onClick={approve}
                                        className="w-full bg-emerald-600 hover:bg-emerald-700"
                                        disabled={processing}
                                    >
                                        <CircleCheck className="mr-2 h-4 w-4" />
                                        Approve Product
                                    </Button>
                                    <Button
                                        onClick={reject}
                                        variant="outline"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        <CircleX className="mr-2 h-4 w-4" />
                                        Reject Product
                                    </Button>
                                </>
                            )}

                            {product.status === 'published' && (
                                <Button
                                    onClick={archive}
                                    variant="outline"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    Archive Product
                                </Button>
                            )}

                            {product.status === 'pending_review' && (
                                <div className="space-y-3 border-t border-border pt-3">
                                    <div>
                                        <label className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                            Rejection Reason
                                        </label>
                                        <select
                                            value={data.rejection_reason}
                                            onChange={(e) =>
                                                setData(
                                                    'rejection_reason',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-2 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
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
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Media Modal */}
            <Dialog open={mediaOpen} onOpenChange={setMediaOpen}>
                <DialogContent className="max-w-4xl">
                    {product.media[mediaIndex] && (
                        <div className="relative">
                            {product.media[mediaIndex].type === 'image' ? (
                                <img
                                    src={product.media[mediaIndex].url}
                                    alt={`Product media ${mediaIndex + 1}`}
                                    className="h-auto w-full"
                                />
                            ) : (
                                <video
                                    src={product.media[mediaIndex].url}
                                    controls
                                    className="h-auto w-full"
                                />
                            )}
                            <button
                                onClick={() => setMediaOpen(false)}
                                className="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

AdminProductShow.layout = (page: ReactNode) => <AdminLayout children={page} />;

export default AdminProductShow;
