import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    ExternalLink,
    ImageOff,
    Loader2,
    Search,
    Store as StoreIcon,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { PageProps, PaginatedData } from '@/types';

type VerificationStatus = 'pending' | 'approved' | 'rejected';

interface StoreRow {
    id: number;
    store_name: string;
    slug: string;
    type: string;
    logo: string | null;
    description: string | null;
    verification_status: VerificationStatus;
    rejection_reason: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    created_at: string | null;
    products_count: number;
    missing_fields: string[];
    category: string | null;
    city: string | null;
    owner: { id: number; name: string; email: string | null } | null;
}

const STATUS_LABELS: Record<VerificationStatus, string> = {
    pending: 'En attente',
    approved: 'Validés',
    rejected: 'Refusés',
};

const STATUS_PILL: Record<VerificationStatus, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
};

const STATUS_PILL_LABELS: Record<VerificationStatus, string> = {
    pending: 'En attente',
    approved: 'Validé',
    rejected: 'Refusé',
};

const TYPE_LABELS: Record<string, string> = {
    artist: 'Artisan',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

const FIELD_LABELS: Record<string, string> = {
    store_name: 'nom',
    category_id: 'métier',
    city_id: 'ville',
    description: 'présentation',
};

interface Filters {
    search: string;
    status: string;
}

const Index = ({
    stores,
    filters,
    statusCounts,
    statuses,
}: PageProps & {
    stores: PaginatedData<StoreRow>;
    filters: Filters;
    statusCounts: Record<string, number>;
    statuses: VerificationStatus[];
}) => {
    const [search, setSearch] = useState(filters.search);
    const [searching, setSearching] = useState(false);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.stores.index'),
                { status: filters.status, search: search || undefined },
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

    const setStatus = (status: string) => {
        router.get(
            route('admin.stores.index'),
            { search: search || undefined, status: status || undefined },
            { preserveState: true },
        );
    };

    const flashHandlers = {
        preserveScroll: true,
        onSuccess: (page: { props: object }) => {
            const flash = (page.props as unknown as PageProps).flash;

            if (flash?.error) {
                toast.error(flash.error);
            } else if (flash?.success) {
                toast.success(flash.success);
            }
        },
        onError: () => toast.error('Une erreur est survenue.'),
    };

    const approve = (store: StoreRow) => {
        if (confirm(`Valider l’atelier « ${store.store_name} » ?`)) {
            router.patch(
                route('admin.stores.approve', store.id),
                undefined,
                flashHandlers,
            );
        }
    };

    const reject = (store: StoreRow) => {
        const reason = window.prompt(
            `Pourquoi « ${store.store_name} » est-il refusé ?\nCe message est envoyé au vendeur (10 caractères minimum).`,
            store.rejection_reason ?? '',
        );

        // null means the dialog was dismissed — leave the shop alone.
        if (reason === null) {
            return;
        }

        if (reason.trim().length < 10) {
            toast.error('Le motif doit faire au moins 10 caractères.');

            return;
        }

        router.patch(
            route('admin.stores.reject', store.id),
            { reason: reason.trim() },
            flashHandlers,
        );
    };

    const totalCount = Object.values(statusCounts).reduce((s, n) => s + n, 0);
    const tabs = [
        { value: '', label: 'Tous', count: totalCount },
        ...statuses.map((s) => ({
            value: s,
            label: STATUS_LABELS[s],
            count: statusCounts[s] ?? 0,
        })),
    ];
    const pendingCount = statusCounts.pending ?? 0;

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Ateliers" />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Ateliers
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {totalCount} atelier{totalCount > 1 ? 's' : ''} au
                        total.
                    </p>
                </div>
            </header>

            {/* Review queue banner — only while there is work to do. */}
            {pendingCount > 0 && (
                <button
                    type="button"
                    onClick={() =>
                        setStatus(filters.status === 'pending' ? '' : 'pending')
                    }
                    className={`flex items-center gap-3 rounded-xl border px-5 py-3.5 text-left transition ${
                        filters.status === 'pending'
                            ? 'border-amber-400 bg-amber-100'
                            : 'border-amber-300/60 bg-amber-50 hover:bg-amber-100'
                    }`}
                >
                    <Clock className="h-5 w-5 shrink-0 text-amber-600" />
                    <span className="text-sm text-ink">
                        <span className="font-medium">
                            {pendingCount} atelier
                            {pendingCount > 1 ? 's' : ''} en attente de
                            validation
                        </span>{' '}
                        <span className="text-ink-soft">
                            {filters.status === 'pending'
                                ? '— cliquez pour tout afficher'
                                : 'ne peuvent pas encore vendre.'}
                        </span>
                    </span>
                </button>
            )}

            {/* ── Status tabs ──────────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = filters.status === tab.value;

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

            {/* ── Search ───────────────────────────────────── */}
            <div className="relative max-w-md">
                {searching ? (
                    <Loader2 className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 animate-spin text-ink-soft/50" />
                ) : (
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                )}
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Rechercher un atelier…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Atelier</TableHead>
                            <TableHead>Artisan</TableHead>
                            <TableHead>Métier &amp; ville</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead className="text-right">
                                Produits
                            </TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {stores.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="h-32 text-center"
                                >
                                    <StoreIcon className="mx-auto h-8 w-8 text-ink/15" />
                                    <p className="mt-3 text-sm text-ink-soft">
                                        Aucun atelier ne correspond.
                                    </p>
                                </TableCell>
                            </TableRow>
                        ) : (
                            stores.data.map((store) => (
                                <TableRow key={store.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-muted">
                                                {store.logo ? (
                                                    <img
                                                        src={store.logo}
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
                                                <p className="truncate font-medium text-ink">
                                                    {store.store_name}
                                                </p>
                                                <p className="mt-0.5 text-xs text-ink-soft">
                                                    {TYPE_LABELS[store.type] ??
                                                        store.type}
                                                    {store.submitted_at &&
                                                        ` · déposé le ${formatDate(store.submitted_at)}`}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        {store.owner ? (
                                            <div className="flex flex-col text-sm">
                                                <Link
                                                    href={route(
                                                        'admin.users.show',
                                                        store.owner.id,
                                                    )}
                                                    className="text-ink hover:underline"
                                                >
                                                    {store.owner.name}
                                                </Link>
                                                <span className="text-xs text-ink-soft">
                                                    {store.owner.email ?? '—'}
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-sm text-ink-soft/60">
                                                —
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className="text-sm text-ink-soft">
                                        {store.category ?? (
                                            <span className="text-ink-soft/50">
                                                métier manquant
                                            </span>
                                        )}
                                        <br />
                                        <span className="text-xs">
                                            {store.city ?? (
                                                <span className="text-ink-soft/50">
                                                    ville manquante
                                                </span>
                                            )}
                                        </span>
                                    </TableCell>

                                    <TableCell>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_PILL[store.verification_status]}`}
                                        >
                                            {
                                                STATUS_PILL_LABELS[
                                                    store.verification_status
                                                ]
                                            }
                                        </span>

                                        {/* Why this shop is not reviewable
                                            yet, without opening it. */}
                                        {store.missing_fields.length > 0 && (
                                            <p className="mt-1 text-[11px] text-amber-700">
                                                Manque :{' '}
                                                {store.missing_fields
                                                    .map(
                                                        (f) =>
                                                            FIELD_LABELS[f] ??
                                                            f,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        )}

                                        {store.verification_status ===
                                            'rejected' &&
                                            store.rejection_reason && (
                                                <p
                                                    className="mt-1 max-w-52 truncate text-[11px] text-rose-700"
                                                    title={
                                                        store.rejection_reason
                                                    }
                                                >
                                                    {store.rejection_reason}
                                                </p>
                                            )}
                                    </TableCell>

                                    <TableCell className="text-right text-sm text-ink">
                                        {store.products_count}
                                    </TableCell>

                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1.5">
                                            {store.verification_status ===
                                                'approved' && (
                                                <a
                                                    href={route(
                                                        'stores.show',
                                                        store.slug,
                                                    )}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="Voir la boutique"
                                                    className="rounded-md p-2 text-ink-soft transition-colors hover:bg-muted hover:text-ink"
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                </a>
                                            )}

                                            {/* Approving an incomplete shop
                                                would put a blank storefront in
                                                front of buyers — the endpoint
                                                refuses it too. */}
                                            {store.verification_status !==
                                                'approved' &&
                                                store.missing_fields.length ===
                                                    0 && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            approve(store)
                                                        }
                                                        className="h-8 gap-1.5"
                                                    >
                                                        <Check className="h-3.5 w-3.5" />
                                                        Valider
                                                    </Button>
                                                )}

                                            {store.verification_status !==
                                                'rejected' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        reject(store)
                                                    }
                                                    className="h-8 gap-1.5 text-destructive hover:text-destructive"
                                                >
                                                    <X className="h-3.5 w-3.5" />
                                                    Refuser
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {stores.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {stores.from}–{stores.to} sur {stores.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!stores.prev_page_url}
                                onClick={() =>
                                    router.get(
                                        stores.prev_page_url!,
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
                                disabled={!stores.next_page_url}
                                onClick={() =>
                                    router.get(
                                        stores.next_page_url!,
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

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
