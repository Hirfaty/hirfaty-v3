import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    EllipsisVertical,
    Eye,
    ImageOff,
    Lock,
    Package,
    Pencil,
    PlusCircle,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SellerLayout from '@/layouts/seller/layout';
import { formatDate } from '@/lib/utils';
import type { PageProps, PaginatedData } from '@/types';

interface ProductRow {
    id: number;
    name: string;
    price: number;
    stock: number;
    status: string;
    rejection_reason: string | null;
    created_at: string;
    category: { id: number; name: string } | null;
    images: Array<{ url: string }>;
}

/** Mirrors the Product status lifecycle on the model. */
const STATUS: Record<string, { label: string; className: string }> = {
    draft: { label: 'Brouillon', className: 'bg-slate-100 text-slate-700' },
    pending_review: {
        label: 'En revue',
        className: 'bg-amber-100 text-amber-700',
    },
    published: {
        label: 'En ligne',
        className: 'bg-emerald-100 text-emerald-700',
    },
    rejected: { label: 'Refusé', className: 'bg-rose-100 text-rose-700' },
    archived: { label: 'Archivé', className: 'bg-slate-100 text-slate-500' },
};

const money = (n: number) =>
    `${new Intl.NumberFormat('fr-MA').format(Math.round(n))} MAD`;

const Index = ({
    products,
    flash,
    sellerStore,
}: PageProps & { products: PaginatedData<ProductRow> }) => {
    const canSell = sellerStore?.can_sell ?? false;

    /*
     * `store.sellable` redirects here with a flash when a seller reaches a
     * blocked route directly (a bookmarked /create, say). Surfacing it means
     * the bounce is explained rather than looking like a dead link.
     */
    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error]);

    const handleDelete = (product: ProductRow) => {
        if (
            confirm(
                `Supprimer « ${product.name} » ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('seller.products.destroy', product.id), {
                preserveScroll: true,
                onSuccess: (page) => {
                    const f = (page.props as unknown as PageProps).flash;

                    if (f?.error) {
                        toast.error(f.error);
                    } else if (f?.success) {
                        toast.success(f.success);
                    }
                },
            });
        }
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Produits" />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Produits
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {products.total} produit
                        {products.total > 1 ? 's' : ''} dans votre catalogue.
                    </p>
                </div>

                {/*
                    Disabled-with-a-reason rather than hidden: this is the
                    screen a blocked seller is redirected to, so the button
                    belongs where they expect it and says why it is inert.
                 */}
                {canSell ? (
                    <Link
                        href={route('seller.products.create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                    >
                        <PlusCircle className="h-4 w-4" />
                        Ajouter un produit
                    </Link>
                ) : (
                    <span
                        title="Votre atelier doit être complété et validé par Hirfaty avant de pouvoir publier."
                        className="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-border bg-muted px-4 py-2.5 text-sm font-medium text-ink-soft"
                    >
                        <Lock className="h-4 w-4" />
                        Ajouter un produit
                    </span>
                )}
            </header>

            {/* Standing explanation of the product review step, so "En revue"
                on a row below is never a surprise. */}
            <div className="flex items-start gap-3 rounded-xl border border-border bg-card px-5 py-3.5 shadow-sm">
                <Clock className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                <p className="text-sm text-ink-soft">
                    Chaque produit est relu par l’équipe Hirfaty avant d’être
                    visible par les acheteurs. Comptez un à deux jours ouvrés.
                </p>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Produit</TableHead>
                            <TableHead>Catégorie</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead className="text-right">Prix</TableHead>
                            <TableHead className="text-right">Stock</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {products.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="h-40 text-center"
                                >
                                    <Package className="mx-auto h-8 w-8 text-ink/15" />
                                    <p className="mt-3 text-sm text-ink-soft">
                                        {canSell
                                            ? 'Aucun produit pour le moment.'
                                            : 'Votre atelier doit être validé avant de pouvoir ajouter des produits.'}
                                    </p>
                                </TableCell>
                            </TableRow>
                        ) : (
                            products.data.map((product) => {
                                const meta = STATUS[product.status] ?? {
                                    label: product.status,
                                    className: 'bg-slate-100 text-slate-700',
                                };

                                return (
                                    <TableRow key={product.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-muted">
                                                    {product.images?.[0] ? (
                                                        <img
                                                            src={
                                                                product
                                                                    .images[0]
                                                                    .url
                                                            }
                                                            alt=""
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center text-ink/20">
                                                            <ImageOff className="h-4 w-4" />
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <Link
                                                        href={route(
                                                            'seller.products.show',
                                                            product.id,
                                                        )}
                                                        className="block truncate font-medium text-ink hover:underline"
                                                    >
                                                        {product.name}
                                                    </Link>
                                                    <p className="mt-0.5 text-xs text-ink-soft">
                                                        Ajouté le{' '}
                                                        {formatDate(
                                                            product.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>

                                        <TableCell className="text-sm text-ink-soft">
                                            {product.category?.name ?? '—'}
                                        </TableCell>

                                        <TableCell>
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
                                            >
                                                {meta.label}
                                            </span>
                                            {product.status === 'rejected' &&
                                                product.rejection_reason && (
                                                    <p
                                                        className="mt-1 max-w-52 truncate text-[11px] text-rose-700"
                                                        title={
                                                            product.rejection_reason
                                                        }
                                                    >
                                                        {
                                                            product.rejection_reason
                                                        }
                                                    </p>
                                                )}
                                        </TableCell>

                                        <TableCell className="text-right text-sm text-ink tabular-nums">
                                            {money(product.price)}
                                        </TableCell>

                                        <TableCell className="text-right text-sm tabular-nums">
                                            <span
                                                className={
                                                    product.stock === 0
                                                        ? 'text-rose-600'
                                                        : 'text-ink'
                                                }
                                            >
                                                {product.stock}
                                            </span>
                                        </TableCell>

                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                    >
                                                        <EllipsisVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={route(
                                                                'seller.products.show',
                                                                product.id,
                                                            )}
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                            Voir
                                                        </Link>
                                                    </DropdownMenuItem>

                                                    {/* Edit and delete are
                                                        blocked server-side by
                                                        `store.sellable`; hiding
                                                        them avoids offering a
                                                        link that only bounces. */}
                                                    {canSell && (
                                                        <>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={route(
                                                                        'seller.products.edit',
                                                                        product.id,
                                                                    )}
                                                                    className="flex items-center gap-2"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    Modifier
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        product,
                                                                    )
                                                                }
                                                                className="flex items-center gap-2 text-destructive"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                                Supprimer
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>

                {products.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {products.from}–{products.to} sur {products.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!products.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        products.prev_page_url!,
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!products.next_page_url}
                                onClick={() =>
                                    router.get(
                                        products.next_page_url!,
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

Index.layout = (page: ReactNode) => <SellerLayout>{page}</SellerLayout>;
export default Index;
