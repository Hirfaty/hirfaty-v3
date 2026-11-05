import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    CircleCheck,
    CircleX,
    Eye,
    ImageOff,
    ImageUp,
    Loader2,
    Package,
    RefreshCw,
    Search,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import ExportMenu from '@/components/admin/export-menu';
import QuickPhotoDialog, {
    hasBrokenPhoto,
} from '@/components/admin/quick-photo-dialog';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { WarmFill } from '@/components/WarmFill';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { frenchName } from '@/lib/category';
import { formatDate, formatRelativeTime } from '@/lib/utils';
import type {
    Category,
    Media,
    PageProps,
    PaginatedData,
    Product,
    ProductStatus,
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

export const REJECTION_REASONS = [
    'Photos de mauvaise qualité',
    'Description incomplète ou trompeuse',
    'Prix non conforme aux standards',
    'Produit non artisanal',
    'Catégorie incorrecte',
    'Contenu interdit ou inapproprié',
    "Doublon d'une annonce existante",
    'Autre',
] as const;

type ListedProduct = Product & {
    media: Media[];
    category: Category;
    order_items_count: number;
    /** Active variants' total, or the product's own stock without any. */
    listed_stock: number;
};

/** The values are AdminProductListService::SORTS; empty is its default. */
const SORT_GROUPS = [
    {
        label: 'Date',
        options: [
            { value: '', label: 'Plus récents' },
            { value: 'oldest', label: 'Plus anciens' },
            { value: 'updated', label: 'Modifiés récemment' },
        ],
    },
    {
        label: 'Nom',
        options: [
            { value: 'name_asc', label: 'De A à Z' },
            { value: 'name_desc', label: 'De Z à A' },
        ],
    },
    {
        label: 'Prix',
        options: [
            { value: 'price_asc', label: 'Prix croissant' },
            { value: 'price_desc', label: 'Prix décroissant' },
        ],
    },
    {
        label: 'Stock',
        options: [
            { value: 'stock_asc', label: 'Stock le plus bas' },
            { value: 'stock_desc', label: 'Stock le plus haut' },
        ],
    },
    {
        label: 'Ventes',
        options: [{ value: 'sales', label: 'Plus vendus' }],
    },
];

interface SortFigure {
    label: string;
    value: (product: ListedProduct) => string | number;
}

const PRICE_FIGURE: SortFigure = {
    label: 'Prix',
    value: (p) => {
        const price = p.sale_price ?? p.base_price;

        return price === null ? 'À définir' : `${price} MAD`;
    },
};

const STOCK_FIGURE: SortFigure = {
    label: 'Stock',
    value: (p) => p.listed_stock,
};

// The table has no room for these all the time, but a list ordered by a figure
// nobody can see looks random, so sorting on one brings its column in.
const SORT_FIGURES: Partial<Record<string, SortFigure>> = {
    price_asc: PRICE_FIGURE,
    price_desc: PRICE_FIGURE,
    stock_asc: STOCK_FIGURE,
    stock_desc: STOCK_FIGURE,
    sales: { label: 'Ventes', value: (p) => p.order_items_count },
};

interface AdminProductsProps {
    products: PaginatedData<ListedProduct>;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
        images?: 'broken' | 'none';
        price?: 'with' | 'without';
        stock?: 'in' | 'out';
        variants?: 'with' | 'without';
        sort?: string;
    };
    totalProducts: number;
    statusCounts: Record<ProductStatus, number>;
    /** Matches the other filters, flagged by the nightly `media:check`. */
    brokenImagesCount: number;
    /** When any photo was last checked; null if the check never ran. */
    imagesCheckedAt: string | null;
}

const SELECT_CLASS =
    'rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

function AdminProducts({
    products,
    categories,
    filters,
    totalProducts,
    statusCounts,
    brokenImagesCount,
    imagesCheckedAt,
}: AdminProductsProps) {
    const { t } = useTranslation();
    const [rawSelectedId, setSelectedId] = useState<number | null>(null);
    const [note, setNote] = useState('');
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
    // Skip the debounced effect's own request on first render — otherwise
    // landing with ?search= already set fires a redundant fetch.
    const isFirstRender = useRef(true);

    const { data, setData, patch } = useForm<{ rejection_reason: string }>({
        rejection_reason: REJECTION_REASONS[0],
    });

    // Derived, not synced via effect: whenever the page's product list
    // changes (filter, search, pagination), fall back to the first pending
    // review — or just the first row — without a setState-in-effect render pass.
    const selectedId = useMemo(() => {
        if (
            rawSelectedId !== null &&
            products.data.some((p) => p.id === rawSelectedId)
        ) {
            return rawSelectedId;
        }

        return (
            products.data.find((p) => p.status === 'pending_review')?.id ??
            products.data[0]?.id ??
            null
        );
    }, [rawSelectedId, products.data]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.products.index'),
                { ...filters, search: search || undefined, page: 1 },
                {
                    preserveState: true,
                    replace: true,
                    onFinish: () => setSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const setStatus = (status: string | null) => {
        router.get(
            route('admin.products.index'),
            { ...filters, status: status ?? undefined, page: 1 },
            { preserveState: true },
        );
    };

    const setFilter = (
        key: 'category_id' | 'images' | 'price' | 'stock' | 'variants' | 'sort',
        value: string | undefined,
    ) => {
        router.get(
            route('admin.products.index'),
            { ...filters, [key]: value || undefined, page: 1 },
            { preserveState: true },
        );
    };

    const sortFigure = SORT_FIGURES[filters.sort ?? ''];
    // The date beside each row is the one it is ordered by.
    const showsUpdatedDate = filters.sort === 'updated';

    // Clicking the active images button again clears it.
    const toggleImages = (value: 'broken' | 'none') =>
        setFilter('images', filters.images === value ? undefined : value);

    const [checkingImages, setCheckingImages] = useState(false);

    const checkImagesNow = () => {
        router.post(
            route('admin.products.check-images'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setCheckingImages(true),
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;
                    toast.success(flash?.success ?? 'Vérification lancée.');
                },
                onFinish: () => setCheckingImages(false),
            },
        );
    };

    // Only what the dialog needs to open: it loads the photos itself, so it
    // stays open when a fixed product drops out of a filtered list.
    const [photoProduct, setPhotoProduct] = useState<{
        id: number;
        name: string;
    } | null>(null);

    const tabs = useMemo(
        () =>
            [
                { value: null, label: 'Tous', count: totalProducts },
                {
                    value: 'pending_review',
                    label: 'En attente',
                    count: statusCounts.pending_review,
                },
                {
                    value: 'published',
                    label: 'Publiés',
                    count: statusCounts.published,
                },
                {
                    value: 'draft',
                    label: 'Brouillons',
                    count: statusCounts.draft,
                },
                {
                    value: 'rejected',
                    label: 'Rejetés',
                    count: statusCounts.rejected,
                },
                {
                    value: 'archived',
                    label: 'Archivés',
                    count: statusCounts.archived,
                },
            ] as const,
        [totalProducts, statusCounts],
    );

    const selected: (Product & { media: Media[] }) | undefined =
        products.data.find((p) => p.id === selectedId);

    const approve = () => {
        if (selected) {
            router.patch(route('admin.products.approve', selected.id));
        }
    };
    const reject = () => {
        if (selected) {
            patch(route('admin.products.reject', selected.id));
        }
    };

    // ── Checkbox selection for bulk actions ─────────────────────
    // Kept separate from `selectedId` above: that one drives the review
    // panel on the right, this one drives which rows get acted on together.
    // Each id remembers its status so a selection spanning several pages
    // still knows which bulk actions apply to it.
    const [checkedIds, setCheckedIds] = useState<Map<number, ProductStatus>>(
        new Map(),
    );

    const toggleChecked = (product: Product) =>
        setCheckedIds((prev) => {
            const next = new Map(prev);

            if (next.has(product.id)) {
                next.delete(product.id);
            } else {
                next.set(product.id, product.status);
            }

            return next;
        });

    const pageIds = products.data.map((p) => p.id);
    const checkedOnPage = pageIds.filter((id) => checkedIds.has(id));
    const allOnPageChecked =
        pageIds.length > 0 && checkedOnPage.length === pageIds.length;
    const someOnPageChecked = checkedOnPage.length > 0 && !allOnPageChecked;

    const toggleAllOnPage = () =>
        setCheckedIds((prev) => {
            const next = new Map(prev);

            if (allOnPageChecked) {
                pageIds.forEach((id) => next.delete(id));
            } else {
                products.data.forEach((p) => next.set(p.id, p.status));
            }

            return next;
        });

    // Publish / archive are offered only when every checked product shares
    // the one status they move from; a mixed selection gets delete alone.
    // A row on the current page reports its live status, in case it changed
    // (approved from the side panel, say) after it was checked.
    const checkedStatuses = new Set(
        Array.from(
            checkedIds,
            ([id, status]) =>
                products.data.find((p) => p.id === id)?.status ?? status,
        ),
    );
    const sharedStatus =
        checkedStatuses.size === 1 ? Array.from(checkedStatuses)[0] : null;
    const bulkStatusAction: 'publish' | 'archive' | null =
        sharedStatus === 'archived'
            ? 'publish'
            : sharedStatus === 'published'
              ? 'archive'
              : null;
    const [bulkStatusProcessing, setBulkStatusProcessing] = useState(false);

    const applyBulkStatus = (action: 'publish' | 'archive') => {
        router.patch(
            route('admin.products.bulk-status'),
            { action, ids: Array.from(checkedIds.keys()) },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setBulkStatusProcessing(true),
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    if (flash?.error) {
                        toast.error(flash.error);

                        return;
                    }

                    toast.success(
                        flash?.success ??
                            (action === 'publish'
                                ? 'Produits publiés.'
                                : 'Produits archivés.'),
                    );
                    setCheckedIds(new Map());
                },
                onFinish: () => setBulkStatusProcessing(false),
            },
        );
    };

    // ── Delete (single row and bulk) ────────────────────────────
    // The dialogs' open flags are kept separate from the data they display:
    // Radix keeps a dialog mounted through its close animation, so clearing
    // `deleteTarget`/`checkedIds` on close would flash an empty name or a
    // "0 products" count for the last frames of that animation.
    const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
    const [deleteOneOpen, setDeleteOneOpen] = useState(false);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkDeleteCount, setBulkDeleteCount] = useState(0);
    // Bulk delete is confirmed with the admin's own password.
    const [bulkDeletePassword, setBulkDeletePassword] = useState('');
    const [bulkDeleteError, setBulkDeleteError] = useState<string>();
    const [bulkDeleteProcessing, setBulkDeleteProcessing] = useState(false);

    const askDeleteOne = (product: Product) => {
        setDeleteTarget(product);
        setDeleteOneOpen(true);
    };

    const askBulkDelete = () => {
        setBulkDeleteCount(checkedIds.size);
        setBulkDeletePassword('');
        setBulkDeleteError(undefined);
        setBulkDeleteOpen(true);
    };

    const confirmDeleteOne = () => {
        if (!deleteTarget) {
            return;
        }

        const deletedId = deleteTarget.id;

        router.delete(route('admin.products.destroy', deletedId), {
            // The destroy endpoint replies 200 either way (deleted, or
            // blocked because the product has order history) — the outcome
            // only shows up in `flash`, so it has to be read here rather
            // than assumed from the request merely succeeding.
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);

                    return;
                }

                toast.success(flash?.success ?? 'Produit supprimé.');
                setCheckedIds((prev) => {
                    const next = new Map(prev);
                    next.delete(deletedId);

                    return next;
                });
            },
            onFinish: () => setDeleteOneOpen(false),
        });
    };

    const confirmBulkDelete = (e: FormEvent) => {
        e.preventDefault();

        router.delete(route('admin.products.bulk-destroy'), {
            data: {
                ids: Array.from(checkedIds.keys()),
                password: bulkDeletePassword,
            },
            // Keep the dialog (and what was typed) on screen when the
            // password is refused, so the admin can simply try again.
            preserveState: true,
            preserveScroll: true,
            onStart: () => setBulkDeleteProcessing(true),
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;
                toast.success(flash?.success ?? 'Produits supprimés.');
                setCheckedIds(new Map());
                setBulkDeleteOpen(false);
            },
            onError: (errors) => {
                setBulkDeletePassword('');
                setBulkDeleteError(
                    errors.password
                        ? 'Mot de passe incorrect.'
                        : (errors.ids ?? 'La suppression a échoué.'),
                );
            },
            onFinish: () => setBulkDeleteProcessing(false),
        });
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.products')} />
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Produits
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {totalProducts} produit
                        {totalProducts > 1 ? 's' : ''} · {categories.length}{' '}
                        catégorie{categories.length > 1 ? 's' : ''}
                    </p>
                </div>
                <ExportMenu
                    routeName="admin.products.export"
                    filters={filters}
                />
            </header>

            {/* ── Status tabs ──────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = (filters.status ?? null) === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setStatus(tab.value)}
                            className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-xs ${
                                    active
                                        ? 'bg-white/20 text-white'
                                        : 'bg-muted text-ink-soft'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* ── Search & filters ─────────────────────────── */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative max-w-md min-w-60 flex-1">
                    {searching ? (
                        <Loader2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 animate-spin text-ink-soft/50" />
                    ) : (
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    )}
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Nom du produit, SKU ou boutique…"
                        className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                    />
                </div>
                <select
                    value={filters.category_id ?? ''}
                    onChange={(e) => setFilter('category_id', e.target.value)}
                    className={SELECT_CLASS}
                >
                    <option value="">Toutes les catégories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {frenchName(c)}
                        </option>
                    ))}
                </select>
                <select
                    value={filters.price ?? ''}
                    onChange={(e) => setFilter('price', e.target.value)}
                    aria-label="Filtrer par prix"
                    className={SELECT_CLASS}
                >
                    <option value="">Tous les prix</option>
                    <option value="with">Avec prix</option>
                    <option value="without">Sans prix</option>
                </select>
                <select
                    value={filters.stock ?? ''}
                    onChange={(e) => setFilter('stock', e.target.value)}
                    aria-label="Filtrer par stock"
                    className={SELECT_CLASS}
                >
                    <option value="">Tout le stock</option>
                    <option value="in">En stock</option>
                    <option value="out">En rupture</option>
                </select>
                <select
                    value={filters.variants ?? ''}
                    onChange={(e) => setFilter('variants', e.target.value)}
                    aria-label="Filtrer par déclinaisons"
                    className={SELECT_CLASS}
                >
                    <option value="">Avec ou sans déclinaisons</option>
                    <option value="with">Avec déclinaisons</option>
                    <option value="without">Sans déclinaison</option>
                </select>
                <div className="flex items-center gap-2 text-sm text-ink-soft">
                    <span>Trier par</span>
                    <select
                        value={filters.sort ?? ''}
                        onChange={(e) => setFilter('sort', e.target.value)}
                        aria-label="Trier les produits"
                        className={SELECT_CLASS}
                    >
                        {SORT_GROUPS.map((group) => (
                            <optgroup key={group.label} label={group.label}>
                                {group.options.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                </div>
                <button
                    type="button"
                    onClick={() => toggleImages('broken')}
                    aria-pressed={filters.images === 'broken'}
                    title="Photos introuvables lors de la dernière vérification"
                    className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2.5 text-sm font-medium transition ${
                        filters.images === 'broken'
                            ? 'border-terracotta bg-terracotta text-white'
                            : 'border-border bg-card text-ink-soft hover:border-terracotta/40 hover:text-terracotta'
                    }`}
                >
                    <ImageOff className="h-4 w-4" />
                    Images cassées
                    <span
                        className={`rounded-full px-1.5 py-0.5 text-xs ${
                            filters.images === 'broken'
                                ? 'bg-white/20 text-white'
                                : 'bg-muted text-ink-soft'
                        }`}
                    >
                        {brokenImagesCount}
                    </span>
                </button>
                <button
                    type="button"
                    onClick={() => toggleImages('none')}
                    aria-pressed={filters.images === 'none'}
                    className={`inline-flex items-center gap-2 rounded-lg border px-3.5 py-2.5 text-sm font-medium transition ${
                        filters.images === 'none'
                            ? 'border-ink bg-ink text-white'
                            : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                    }`}
                >
                    Sans photo
                </button>
                <div className="flex items-center gap-2 text-xs text-ink-soft">
                    <button
                        type="button"
                        onClick={checkImagesNow}
                        disabled={checkingImages}
                        className="inline-flex items-center gap-1.5 rounded-lg px-2 py-2.5 font-medium text-ink-soft transition hover:text-ink disabled:opacity-50"
                    >
                        <RefreshCw
                            className={`h-3.5 w-3.5 ${checkingImages ? 'animate-spin' : ''}`}
                        />
                        Vérifier maintenant
                    </button>
                    <span>
                        {imagesCheckedAt
                            ? `Photos vérifiées ${formatRelativeTime(imagesCheckedAt)}`
                            : 'Photos jamais vérifiées'}
                    </span>
                </div>
            </div>

            {/* ── Bulk action bar ──────────────────────────── */}
            {checkedIds.size > 0 && (
                <div className="flex items-center justify-between rounded-lg border border-ink/15 bg-ink px-4 py-3 text-white">
                    <p className="text-sm font-medium">
                        {checkedIds.size} produit
                        {checkedIds.size > 1 ? 's' : ''} sélectionné
                        {checkedIds.size > 1 ? 's' : ''}
                    </p>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setCheckedIds(new Map())}
                            className="text-sm text-white/70 transition hover:text-white"
                        >
                            Annuler
                        </button>
                        {bulkStatusAction && (
                            <button
                                onClick={() =>
                                    applyBulkStatus(bulkStatusAction)
                                }
                                disabled={bulkStatusProcessing}
                                className="inline-flex items-center gap-2 rounded-lg border border-white/30 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-white/10 disabled:opacity-50"
                            >
                                {bulkStatusProcessing ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : bulkStatusAction === 'publish' ? (
                                    <CircleCheck className="h-4 w-4" />
                                ) : (
                                    <Archive className="h-4 w-4" />
                                )}
                                {bulkStatusAction === 'publish'
                                    ? 'Publier'
                                    : 'Archiver'}
                            </button>
                        )}
                        <button
                            onClick={askBulkDelete}
                            className="inline-flex items-center gap-2 rounded-lg bg-terracotta px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-terracotta/90"
                        >
                            <Trash2 className="h-4 w-4" />
                            Supprimer
                        </button>
                    </div>
                </div>
            )}

            {/* ── Layout ───────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_400px]">
                {/* Left: table */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                                <tr>
                                    <th className="w-10 px-5 py-3">
                                        <Checkbox
                                            checked={
                                                allOnPageChecked
                                                    ? true
                                                    : someOnPageChecked
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={toggleAllOnPage}
                                            disabled={pageIds.length === 0}
                                            aria-label="Sélectionner tous les produits de la page"
                                        />
                                    </th>
                                    <th className="px-5 py-3">Produit</th>
                                    <th className="px-5 py-3">SKU</th>
                                    <th className="px-5 py-3">Catégorie</th>
                                    <th className="px-5 py-3">Boutique</th>
                                    {sortFigure && (
                                        <th className="px-5 py-3">
                                            {sortFigure.label}
                                        </th>
                                    )}
                                    <th className="px-5 py-3">
                                        {showsUpdatedDate
                                            ? 'Modifié le'
                                            : 'Créé le'}
                                    </th>
                                    <th className="px-5 py-3">Statut</th>
                                    <th className="px-5 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {products.data.map((p) => {
                                    const isSel = p.id === selectedId;
                                    const isChecked = checkedIds.has(p.id);

                                    return (
                                        <tr
                                            key={p.id}
                                            onClick={() => setSelectedId(p.id)}
                                            className={`cursor-pointer transition ${isSel ? 'bg-muted/60' : 'hover:bg-muted/30'}`}
                                        >
                                            <td
                                                className="px-5 py-3"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Checkbox
                                                    checked={isChecked}
                                                    onCheckedChange={() =>
                                                        toggleChecked(p)
                                                    }
                                                    aria-label={`Sélectionner ${p.name}`}
                                                />
                                            </td>
                                            <td className="px-5 py-3">
                                                <p className="flex items-center gap-1.5 font-medium text-ink">
                                                    {p.name}
                                                    {hasBrokenPhoto(p) && (
                                                        <ImageOff
                                                            className="h-3.5 w-3.5 shrink-0 text-terracotta"
                                                            aria-label="Photo cassée"
                                                        />
                                                    )}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    #{p.id}
                                                </p>
                                            </td>
                                            <td className="px-5 py-3 font-mono text-xs whitespace-nowrap text-ink-soft">
                                                {p.sku ?? '—'}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.category
                                                    ? frenchName(p.category)
                                                    : '—'}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.store.store_name}
                                            </td>
                                            {sortFigure && (
                                                <td className="px-5 py-3 font-medium whitespace-nowrap text-ink">
                                                    {sortFigure.value(p)}
                                                </td>
                                            )}
                                            <td className="px-5 py-3 text-ink-soft">
                                                {formatDate(
                                                    showsUpdatedDate
                                                        ? p.updated_at
                                                        : p.created_at,
                                                )}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[p.status]}`}
                                                >
                                                    {STATUS_LABEL[p.status]}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-right whitespace-nowrap">
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setPhotoProduct({
                                                            id: p.id,
                                                            name: p.name,
                                                        });
                                                    }}
                                                    aria-label={`Photos de ${p.name}`}
                                                    title="Remplacer ou ajouter des photos"
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-ink-soft transition hover:bg-ink hover:text-white"
                                                >
                                                    <ImageUp className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        askDeleteOne(p);
                                                    }}
                                                    aria-label={`Supprimer ${p.name}`}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-ink-soft transition hover:bg-terracotta hover:text-white"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {products.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={sortFigure ? 9 : 8}
                                            className="px-5 py-10 text-center text-ink-soft"
                                        >
                                            Aucun produit trouvé.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {products.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                            <p className="text-xs text-ink-soft">
                                {products.from}–{products.to} sur{' '}
                                {products.total}
                            </p>
                            <div className="flex items-center gap-1">
                                {products.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm transition ${
                                            link.active
                                                ? 'bg-ink font-medium text-white'
                                                : 'text-ink-soft hover:bg-muted'
                                        } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Right: review panel — pinned under the sticky admin header
                    so it stays in view while the table scrolls. `self-start`
                    is required: grid items stretch by default, which leaves
                    sticky nothing to travel within. */}
                <aside className="space-y-4 xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:self-start xl:overflow-y-auto">
                    {' '}
                    {selected ? (
                        <>
                            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                                <div className="flex items-center gap-2 border-b border-border px-5 py-3.5">
                                    <Eye className="h-4 w-4 text-ink-soft" />
                                    <p className="text-sm font-semibold text-ink">
                                        {selected.name}
                                    </p>
                                </div>

                                <div className="relative aspect-4/3 bg-muted">
                                    {selected.media.length > 0 ? (
                                        <img
                                            src={selected.media[0].url}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <WarmFill
                                            variant="cream"
                                            className="h-full w-full"
                                        />
                                    )}
                                    {selected.media.length > 1 && (
                                        <span className="absolute right-2 bottom-2 rounded-full bg-ink/80 px-2 py-1 text-xs font-medium text-white">
                                            {selected.media.length} photos
                                        </span>
                                    )}
                                </div>

                                <div className="space-y-4 p-5">
                                    <div>
                                        <p className="text-xs font-medium text-ink-soft">
                                            Référence (SKU)
                                        </p>
                                        <p className="mt-1 font-mono text-sm text-ink">
                                            {selected.sku ?? '—'}
                                        </p>
                                    </div>

                                    <div>
                                        <p className="text-xs font-medium text-ink-soft">
                                            Description
                                        </p>
                                        {selected.description ? (
                                            <ProductDescription
                                                html={selected.description}
                                                className="mt-1 text-sm leading-relaxed text-ink"
                                            />
                                        ) : (
                                            <p className="mt-1 text-sm text-ink-soft">
                                                Aucune description.
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-lg border border-border p-3">
                                            <p className="text-xs text-ink-soft">
                                                Prix
                                            </p>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                {selected.sale_price ? (
                                                    <>
                                                        <p className="text-xl font-semibold text-ink">
                                                            {
                                                                selected.sale_price
                                                            }{' '}
                                                            <span className="text-xs font-medium text-ink-soft">
                                                                MAD
                                                            </span>
                                                        </p>
                                                        <p className="text-sm text-ink-soft line-through">
                                                            {
                                                                selected.base_price
                                                            }
                                                        </p>
                                                    </>
                                                ) : selected.base_price ===
                                                  null ? (
                                                    // Unpriced: off the
                                                    // storefront until set.
                                                    <p className="text-xl font-semibold text-amber-700">
                                                        À définir
                                                    </p>
                                                ) : (
                                                    <p className="text-xl font-semibold text-ink">
                                                        {selected.base_price}{' '}
                                                        <span className="text-xs font-medium text-ink-soft">
                                                            MAD
                                                        </span>
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="rounded-lg border border-border p-3">
                                            <p className="text-xs text-ink-soft">
                                                Stock
                                            </p>
                                            {selected.has_variants ? (
                                                <p className="mt-1 text-sm font-medium text-ink-soft">
                                                    Géré par déclinaison
                                                </p>
                                            ) : (
                                                <p className="mt-1 text-xl font-semibold text-ink">
                                                    {selected.stock}{' '}
                                                    <span className="text-xs font-medium text-ink-soft">
                                                        unités
                                                    </span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {selected.status === 'pending_review' ? (
                                        <div className="space-y-3">
                                            <button
                                                onClick={approve}
                                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-ink text-sm font-semibold text-white transition hover:bg-ink/90"
                                            >
                                                <CircleCheck className="h-4 w-4" />
                                                Approuver
                                            </button>
                                            <button
                                                onClick={reject}
                                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border text-sm font-semibold text-ink transition hover:bg-muted"
                                            >
                                                <CircleX className="h-4 w-4" />
                                                Rejeter
                                            </button>

                                            <div className="space-y-3 border-t border-border pt-3">
                                                <div>
                                                    <label className="text-xs font-medium text-ink-soft">
                                                        Motif de rejet
                                                    </label>
                                                    <div className="mt-2 grid grid-cols-2 gap-1.5">
                                                        {REJECTION_REASONS.slice(
                                                            0,
                                                            4,
                                                        ).map((r) => (
                                                            <button
                                                                key={r}
                                                                onClick={() =>
                                                                    setData(
                                                                        'rejection_reason',
                                                                        r,
                                                                    )
                                                                }
                                                                className={`rounded-lg border px-2.5 py-1.5 text-left text-xs transition ${
                                                                    data.rejection_reason ===
                                                                    r
                                                                        ? 'border-ink bg-ink/5 font-medium text-ink'
                                                                        : 'border-border text-ink-soft hover:border-ink/30'
                                                                }`}
                                                            >
                                                                {r}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div>
                                                    <label className="text-xs font-medium text-ink-soft">
                                                        Message interne
                                                        (optionnel)
                                                    </label>
                                                    <textarea
                                                        value={note}
                                                        onChange={(e) =>
                                                            setNote(
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={3}
                                                        placeholder="Ajouter une note pour le vendeur…"
                                                        className="mt-2 w-full resize-none rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink shadow-sm transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-muted/60 p-3 text-sm text-ink-soft">
                                            Ce produit est actuellement{' '}
                                            {STATUS_LABEL[
                                                selected.status
                                            ].toLowerCase()}
                                            .
                                            {selected.rejection_reason && (
                                                <p className="mt-1 text-ink">
                                                    Motif :{' '}
                                                    {selected.rejection_reason}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <Link
                                href={route('admin.products.show', selected.id)}
                                className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-border bg-card text-sm font-medium text-ink shadow-sm transition hover:bg-muted"
                            >
                                <SquareArrowOutUpRight className="h-4 w-4" />
                                Voir la fiche complète
                            </Link>
                        </>
                    ) : (
                        <div className="flex h-64 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card text-ink-soft">
                            <Package className="mb-2 h-10 w-10 opacity-30" />
                            <p className="text-sm">
                                Sélectionnez un produit à examiner
                            </p>
                        </div>
                    )}
                </aside>
            </div>

            {photoProduct && (
                <QuickPhotoDialog
                    key={photoProduct.id}
                    productId={photoProduct.id}
                    productName={photoProduct.name}
                    onClose={() => setPhotoProduct(null)}
                />
            )}

            {/* ── Delete confirmation (single row) ────────────── */}
            <AlertDialog open={deleteOneOpen} onOpenChange={setDeleteOneOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer «&nbsp;{deleteTarget?.name}&nbsp;» ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible : le produit, ses
                            photos et ses déclinaisons seront supprimés
                            définitivement. Un produit ayant déjà été commandé
                            ne peut pas être supprimé — archivez-le à la place.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDeleteOne}
                            className="bg-terracotta text-white hover:bg-terracotta/90"
                        >
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* ── Delete confirmation (bulk) ───────────────────── */}
            <AlertDialog
                open={bulkDeleteOpen}
                onOpenChange={(open) =>
                    !bulkDeleteProcessing && setBulkDeleteOpen(open)
                }
            >
                <AlertDialogContent>
                    {/* A plain submit button rather than AlertDialogAction:
                        that one closes the dialog on click, before the
                        server has had a chance to refuse the password. */}
                    <form onSubmit={confirmBulkDelete} className="grid gap-4">
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Supprimer {bulkDeleteCount} produit
                                {bulkDeleteCount > 1 ? 's' : ''} ?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Cette action est irréversible. Les produits
                                ayant déjà été commandés seront automatiquement
                                ignorés plutôt que supprimés.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="bulk-delete-password">
                                Confirmez avec votre mot de passe
                            </Label>
                            <PasswordInput
                                id="bulk-delete-password"
                                value={bulkDeletePassword}
                                onChange={(e) => {
                                    setBulkDeletePassword(e.target.value);
                                    setBulkDeleteError(undefined);
                                }}
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <InputError message={bulkDeleteError} />
                        </div>
                        <AlertDialogFooter>
                            <AlertDialogCancel
                                type="button"
                                disabled={bulkDeleteProcessing}
                            >
                                Annuler
                            </AlertDialogCancel>
                            <Button
                                type="submit"
                                disabled={
                                    bulkDeleteProcessing ||
                                    bulkDeletePassword === ''
                                }
                                className="bg-terracotta text-white hover:bg-terracotta/90"
                            >
                                {bulkDeleteProcessing && (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                )}
                                Supprimer
                            </Button>
                        </AlertDialogFooter>
                    </form>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

AdminProducts.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default AdminProducts;
