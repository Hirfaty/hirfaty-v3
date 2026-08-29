import { Link, router, useForm } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Eye,
    Loader2,
    Package,
    Search,
    SquareArrowOutUpRight,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { WarmFill } from '@/components/WarmFill';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type {
    Category,
    Media,
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

interface AdminProductsProps {
    products: PaginatedData<Product & { media: Media[]; category: Category }>;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
    };
    totalProducts: number;
    statusCounts: Record<ProductStatus, number>;
}

function AdminProducts({
    products,
    categories,
    filters,
    totalProducts,
    statusCounts,
}: AdminProductsProps) {
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

    const setCategory = (categoryId: string) => {
        router.get(
            route('admin.products.index'),
            { ...filters, category_id: categoryId || undefined, page: 1 },
            { preserveState: true },
        );
    };

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

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
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

            {/* ── Search & category filter ────────────────── */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative max-w-md flex-1">
                    {searching ? (
                        <Loader2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 animate-spin text-ink-soft/50" />
                    ) : (
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    )}
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Nom du produit ou boutique…"
                        className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                    />
                </div>
                <select
                    value={filters.category_id ?? ''}
                    onChange={(e) => setCategory(e.target.value)}
                    className="rounded-lg border border-border bg-card px-3.5 py-2.5 text-sm text-ink shadow-sm transition focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                >
                    <option value="">Toutes les catégories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.translated_name}
                        </option>
                    ))}
                </select>
            </div>

            {/* ── Layout ───────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_400px]">
                {/* Left: table */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                                <tr>
                                    <th className="px-5 py-3">Produit</th>
                                    <th className="px-5 py-3">Catégorie</th>
                                    <th className="px-5 py-3">Boutique</th>
                                    <th className="px-5 py-3">Date</th>
                                    <th className="px-5 py-3 text-right">
                                        Statut
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {products.data.map((p) => {
                                    const isSel = p.id === selectedId;

                                    return (
                                        <tr
                                            key={p.id}
                                            onClick={() => setSelectedId(p.id)}
                                            className={`cursor-pointer transition ${isSel ? 'bg-muted/60' : 'hover:bg-muted/30'}`}
                                        >
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-ink">
                                                    {p.name}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    #{p.id}
                                                </p>
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.category?.translated_name ??
                                                    '—'}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {p.store.store_name}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {formatDate(p.updated_at)}
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[p.status]}`}
                                                >
                                                    {STATUS_LABEL[p.status]}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {products.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
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

                {/* Right: review panel */}
                <aside className="space-y-4">
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
                                            Description
                                        </p>
                                        <p className="mt-1 text-sm leading-relaxed text-ink">
                                            {selected.description ||
                                                'Aucune description.'}
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-lg border border-border p-3">
                                            <p className="text-xs text-ink-soft">
                                                Prix
                                            </p>
                                            <div className="mt-1 flex items-baseline gap-2">
                                                {selected.sale_price ? (
                                                    <>
                                                        <p className="text-lg font-semibold text-ink">
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
                                                ) : (
                                                    <p className="text-lg font-semibold text-ink">
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
                                                <p className="mt-1 text-lg font-semibold text-ink">
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
        </div>
    );
}

AdminProducts.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default AdminProducts;
