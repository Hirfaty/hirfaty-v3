import { Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import {
    Plus,
    Pencil,
    Trash2,
    Eye,
    Package,
    Archive,
    AlertCircle,
    CheckCircle2,
    Clock,
    Search,
    X,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SellerLayout from '@/layouts/seller/layout';
import { cn } from '@/lib/utils';
import type { Media, PaginatedData, Product, ProductStatus } from '@/types';

const statusConfig: Record<
    ProductStatus,
    { label: string; icon: any; className: string }
> = {
    published: {
        label: 'En vente',
        icon: CheckCircle2,
        className: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    },
    pending_review: {
        label: 'En révision',
        icon: Clock,
        className: 'bg-amber-50 text-amber-700 border-amber-200',
    },
    rejected: {
        label: 'Rejeté',
        icon: AlertCircle,
        className: 'bg-rose-50 text-rose-700 border-rose-200',
    },
    archived: {
        label: 'Archivé',
        icon: Archive,
        className: 'bg-slate-50 text-slate-700 border-slate-200',
    },
};

interface ProductProps {
    products: PaginatedData<Product & { images: Media[] }>;
}

function Index({ products }: ProductProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedStatuses, setSelectedStatuses] = useState<ProductStatus[]>(
        [],
    );

    const statuses: ProductStatus[] = [
        'published',
        'pending_review',
        'rejected',
        'archived',
    ];

    const handleDelete = (id: number, name: string) => {
        router.delete(route('seller.products.destroy', id), {
            onSuccess: () => {
                toast.success('Produit supprimé', {
                    description: `« ${name} » a été retiré de votre catalogue.`,
                });
            },
        });
    };

    const toggleStatusFilter = (status: ProductStatus) => {
        setSelectedStatuses((prev) =>
            prev.includes(status)
                ? prev.filter((s) => s !== status)
                : [...prev, status],
        );
    };

    const filteredProducts = useMemo(() => {
        return products.data.filter((p) => {
            const matchesSearch =
                searchTerm === '' ||
                p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                p.id.toString().includes(searchTerm);

            const matchesStatus =
                selectedStatuses.length === 0 ||
                selectedStatuses.includes(p.status);

            return matchesSearch && matchesStatus;
        });
    }, [products.data, searchTerm, selectedStatuses]);

    return (
        <div className="mx-auto max-w-[1400px] px-4 py-10 sm:px-6 lg:px-8">
            <header className="mb-10 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <div className="flex items-center gap-2 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        <span className="inline-block h-px w-8 bg-border" />
                        Gestion de l'Atelier
                    </div>
                    <h1 className="mt-4 font-display text-4xl leading-tight md:text-5xl lg:text-6xl">
                        Votre Catalogue
                    </h1>
                    <p className="mt-4 max-w-md leading-relaxed text-muted-foreground">
                        Gérez vos pièces uniques, suivez vos stocks et mettez en
                        avant votre savoir-faire.
                    </p>
                </div>
                <Button
                    asChild
                    className="h-12 bg-primary px-6 text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90"
                >
                    <Link href={route('seller.products.create')}>
                        <Plus className="me-2 h-5 w-5" />
                        Créer une pièce
                    </Link>
                </Button>
            </header>

            {/* Search and Filters */}
            <div className="mb-8 space-y-4">
                {/* Search Bar */}
                <div className="relative">
                    <Search className="absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="Rechercher par nom ou ID..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full border border-border bg-card/50 py-3 pr-4 pl-12 text-sm placeholder-muted-foreground transition-all focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background focus:outline-none"
                    />
                    {searchTerm && (
                        <button
                            onClick={() => setSearchTerm('')}
                            className="absolute top-1/2 right-4 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    )}
                </div>

                {/* Status Filters */}
                <div className="flex flex-wrap gap-2">
                    {statuses.map((status) => {
                        const config = statusConfig[status];
                        const isSelected = selectedStatuses.includes(status);

                        return (
                            <button
                                key={status}
                                onClick={() => toggleStatusFilter(status)}
                                className={cn(
                                    'rounded-full border px-4 py-2 text-sm font-medium transition-all',
                                    isSelected
                                        ? `${config.className} ring-2 ring-primary ring-offset-2 ring-offset-background`
                                        : 'border-border bg-card/50 text-muted-foreground hover:bg-card hover:text-foreground',
                                )}
                            >
                                {config.label}
                            </button>
                        );
                    })}
                    {selectedStatuses.length > 0 && (
                        <button
                            onClick={() => setSelectedStatuses([])}
                            className="ml-2 rounded-full border border-border bg-card/50 px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Réinitialiser
                        </button>
                    )}
                </div>
            </div>

            <div className="overflow-hidden border border-border bg-card/50 shadow-sm backdrop-blur-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="border-b border-border bg-muted/30 hover:bg-muted/30">
                            <TableHead className="py-5 pl-6 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Produit
                            </TableHead>
                            <TableHead className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Statut
                            </TableHead>
                            <TableHead className="text-end text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Prix
                            </TableHead>
                            <TableHead className="text-end text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Stock
                            </TableHead>
                            <TableHead className="pr-6 text-end text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredProducts.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="py-20 text-center"
                                >
                                    <div className="flex flex-col items-center gap-3">
                                        <div className="flex h-16 w-16 items-center justify-center bg-muted">
                                            <Package className="h-8 w-8 text-muted-foreground/50" />
                                        </div>
                                        <p className="font-display text-lg">
                                            Aucun produit trouvé
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {searchTerm ||
                                            selectedStatuses.length > 0
                                                ? "Essayez d'ajuster vos filtres ou votre recherche."
                                                : 'Commencez par ajouter votre première création.'}
                                        </p>
                                        {(searchTerm ||
                                            selectedStatuses.length > 0) && (
                                            <Button
                                                onClick={() => {
                                                    setSearchTerm('');
                                                    setSelectedStatuses([]);
                                                }}
                                                variant="outline"
                                                className="mt-2 rounded-xl"
                                            >
                                                Réinitialiser les filtres
                                            </Button>
                                        )}
                                        {!searchTerm &&
                                            selectedStatuses.length === 0 && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="mt-2"
                                                >
                                                    <Link
                                                        href={route(
                                                            'seller.products.create',
                                                        )}
                                                    >
                                                        Ajouter un produit
                                                    </Link>
                                                </Button>
                                            )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ) : (
                            filteredProducts.map((p) => {
                                const config = statusConfig[p.status];
                                const StatusIcon = config.icon;

                                return (
                                    <TableRow
                                        key={p.id}
                                        className="group border-border transition-colors hover:bg-muted/20"
                                    >
                                        <TableCell className="py-4 pl-6">
                                            <div className="flex items-center gap-4">
                                                <div className="h-14 w-14 shrink-0 overflow-hidden border border-border bg-muted shadow-sm">
                                                    <img
                                                        src={
                                                            p.images[0]?.url ??
                                                            ''
                                                        }
                                                        alt={p.name}
                                                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                                    />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="font-display text-lg leading-tight">
                                                        {p.name}
                                                    </span>
                                                    <span className="mt-0.5 text-[10px] tracking-wider text-muted-foreground uppercase">
                                                        ID: #{p.id}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold',
                                                    config.className,
                                                )}
                                            >
                                                <StatusIcon className="h-3 w-3" />
                                                {config.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-end font-medium tabular-nums">
                                            {p.base_price}{' '}
                                            <span className="text-[10px] text-muted-foreground">
                                                MAD
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">
                                            <div className="flex flex-col items-end">
                                                <span
                                                    className={cn(
                                                        'font-medium',
                                                        p.stock <= 2
                                                            ? 'text-rose-600'
                                                            : '',
                                                    )}
                                                >
                                                    {p.stock}
                                                </span>
                                                {p.stock <= 2 && (
                                                    <span className="text-[9px] font-bold tracking-tighter text-rose-500 uppercase">
                                                        Bas !
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="pr-6">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    asChild
                                                    size="icon"
                                                    variant="ghost"
                                                    className="h-9 w-9 rounded-lg transition-colors hover:bg-primary/10 hover:text-primary"
                                                    title="Aperçu public"
                                                >
                                                    <Link
                                                        href={route(
                                                            'seller.products.show',
                                                            p.id,
                                                        )}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="icon"
                                                    variant="ghost"
                                                    className="h-9 w-9 rounded-lg transition-colors hover:bg-primary/10 hover:text-primary"
                                                    title="Modifier"
                                                >
                                                    <Link
                                                        href={route(
                                                            'seller.products.edit',
                                                            p.id,
                                                        )}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>

                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-9 w-9 rounded-lg text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                                            title="Supprimer"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent className="rounded-2xl">
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle className="font-display text-2xl">
                                                                Supprimer ce
                                                                produit ?
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription className="text-base">
                                                                Cette action
                                                                retirera «{' '}
                                                                {p.name} » de
                                                                votre boutique.
                                                                Cette opération
                                                                est
                                                                irréversible.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter className="mt-6">
                                                            <AlertDialogCancel className="h-11 rounded-xl">
                                                                Annuler
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        p.id,
                                                                        p.name,
                                                                    )
                                                                }
                                                                className="h-11 rounded-xl bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                            >
                                                                Oui, supprimer
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
            </div>
            {/* Pagination placeholder if needed */}
        </div>
    );
}

Index.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

export default Index;

// import SellerLayout from '@/layouts/seller/layout';
// import React from 'react';
// import { Link } from '@inertiajs/react';
// import { Products } from '@/types';
// import { Product, PaginatedData } from '@/types';

// const Index = ({ pageProps }: { pageProps: PaginatedData<Product> }) => {
//     console.log('products: ', pageProps);
//     return (
//         <div>
//             {pageProps.data.map((e) => {
//                 return (
//                     <div key={e.id} className="grid grid-cols-2">
//                         <img src={e?.images[0]?.url} className="h-24 w-24" />
//                         <Link
//                             href={route('seller.products.edit', e.id)}
//                             className="text-blue-600 hover:text-blue-900"
//                         >
//                             Edit Product
//                         </Link>
//                         <Link href={route('seller.products.destroy', e.id)}>
//                             Delete
//                         </Link>
//                     </div>
//                 );
//             })}
//         </div>

//     );
// };

// Index.layout = (page: React.ReactNode) => <SellerLayout children={page} />;

// export default Index;
