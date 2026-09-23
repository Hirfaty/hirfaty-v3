import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    EllipsisVertical,
    Eye,
    ImageOff,
    Package,
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
import { useTranslation } from '@/hooks/use-translation';
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
    /*
     * `name` is a per-locale map on the model (Category casts it to an array),
     * never a string — rendering it directly threw "objects are not valid as a
     * React child" and blanked the whole page. `translated_name` is the
     * resolved string Category appends for exactly this.
     */
    category: { id: number; translated_name: string } | null;
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
}: PageProps & { products: PaginatedData<ProductRow> }) => {
    const { t } = useTranslation();
    // Nothing on this page writes, but a flashed error can still arrive from
    // elsewhere in the seller area — surface it rather than swallow it.
    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error]);

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.seller.products')} />

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
            </header>

            {/* Says who files a listing, so an artisan with an empty
                catalogue knows to expect a call rather than to go looking
                for an "add a product" button that no longer exists. */}
            <div className="flex items-start gap-3 rounded-xl border border-border bg-card px-5 py-3.5 shadow-sm">
                <Clock className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                <p className="text-sm text-ink-soft">
                    Vos produits sont publiés par l’équipe Hirfaty : nous vous
                    contactons pour rassembler photos, prix et descriptions,
                    puis la pièce apparaît ici une fois en ligne.
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
                                        Aucun produit pour le moment. L’équipe
                                        Hirfaty vous contactera pour publier vos
                                        premières pièces.
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
                                            {product.category
                                                ?.translated_name ?? '—'}
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

                                                    {/* No edit or delete:
                                                        listings are filed and
                                                        maintained by the
                                                        Hirfaty team, so there
                                                        is no seller-side route
                                                        left to link to. */}
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
