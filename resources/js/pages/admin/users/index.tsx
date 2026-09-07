import { Link, router } from '@inertiajs/react';
import {
    Ban,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    EllipsisVertical,
    Eye,
    KeyRound,
    Loader2,
    Package,
    Pencil,
    Search,
    Trash2,
    UserCheck,
    Users,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ResetLinkDialog from '@/components/admin/reset-link-dialog';
import { Badge } from '@/components/ui/badge';
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
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type {
    PageProps,
    PaginatedData,
    ResetLinkPayload,
    StoreType,
    User,
    UserRole,
} from '@/types';

type UserRow = User & {
    products_count: number;
    store: {
        id: number;
        store_name: string;
        type: StoreType;
        city: { name: string } | null;
    } | null;
};

type ProRequestStatus = 'pending' | 'approved' | 'declined';

interface Props {
    users: PaginatedData<UserRow>;
    totalUsers: number;
    roleCounts: Record<'buyer' | 'professional_buyer' | 'seller', number>;
    /*
     * Counted over every role, not just `professional_buyer`: the status
     * outlives the decision — a declined request leaves the account a plain
     * `buyer`, while an approved one is by then `professional_buyer`.
     */
    professionalRequestCounts: Record<ProRequestStatus, number>;
    /** Buyers waiting on a trade decision. Drives the queue banner below. */
    pendingProfessionalCount: number;
    filters: {
        search?: string;
        role?: string;
        professional_request_status?: ProRequestStatus | string;
    };
}

const PRO_REQUEST_BADGE: Record<
    ProRequestStatus,
    { label: string; className: string; Icon: typeof Clock }
> = {
    pending: {
        label: 'Demande en attente',
        className: 'bg-amber-100 text-amber-700',
        Icon: Clock,
    },
    approved: {
        label: 'Compte pro approuvé',
        className: 'bg-emerald-100 text-emerald-700',
        Icon: CheckCircle2,
    },
    declined: {
        label: 'Demande refusée',
        className: 'bg-rose-100 text-rose-700',
        Icon: XCircle,
    },
};

/**
 * What the trade-request lifecycle looks like for this row, if anything.
 *
 * `professional_request_status` is the source of truth once a request
 * exists — it survives the decision, so it is checked ahead of the role.
 * An account promoted straight from the admin's user editor (never went
 * through a request) has no status to show; the old verified/non-vérifié
 * hint covers that case instead.
 */
function ProRequestStatusBadge({ user }: { user: UserRow }) {
    if (user.professional_request_status) {
        const badge = PRO_REQUEST_BADGE[user.professional_request_status];
        const Icon = badge.Icon;

        return (
            <span
                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${badge.className}`}
                title={badge.label}
            >
                <Icon className="h-3 w-3" />
                {badge.label}
            </span>
        );
    }

    if (user.role === 'professional_buyer') {
        return user.professional_verified_at ? (
            <CheckCircle2
                className="h-4 w-4 text-emerald-600"
                aria-label="Vérifié"
            />
        ) : (
            <span
                className="text-[11px] text-ink-soft"
                title="En attente de vérification"
            >
                non vérifié
            </span>
        );
    }

    return null;
}

const ROLE_LABEL: Record<UserRole, string> = {
    buyer: 'Acheteur',
    professional_buyer: 'Acheteur pro',
    seller: 'Vendeur',
    super_admin: 'Administrateur',
};

const ROLE_BADGE: Record<UserRole, string> = {
    buyer: 'bg-slate-100 text-slate-700',
    professional_buyer: 'bg-violet-100 text-violet-700',
    seller: 'bg-emerald-100 text-emerald-700',
    super_admin: 'bg-amber-100 text-amber-700',
};

const STORE_TYPE_LABEL: Record<StoreType, string> = {
    artist: 'Artiste',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

export default function UsersIndex({
    users,
    totalUsers,
    roleCounts,
    professionalRequestCounts,
    pendingProfessionalCount,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
    const [generatingResetId, setGeneratingResetId] = useState<number | null>(
        null,
    );
    const [resetLink, setResetLink] = useState<ResetLinkPayload | null>(null);
    // Skip the debounced effect's own request on first render — otherwise
    // landing on the page with ?search= already set fires a redundant fetch.
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        // Short debounce: instant enough to feel live without a request per
        // keystroke while the admin is still typing a name.
        const timer = setTimeout(() => {
            router.get(
                route('admin.users.index'),
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

    const setRole = (role: string | null) => {
        router.get(
            route('admin.users.index'),
            { ...filters, role: role ?? undefined, page: 1 },
            { preserveState: true },
        );
    };

    const setRequestStatus = (status: ProRequestStatus | null) => {
        router.get(
            route('admin.users.index'),
            {
                ...filters,
                professional_request_status: status ?? undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleBan = (user: User) => {
        if (
            confirm(
                `Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} ${user.first_name} ${user.last_name} ?`,
            )
        ) {
            router.patch(route('admin.users.ban', user.id), undefined, {
                preserveScroll: true,
            });
        }
    };

    const handleGenerateResetLink = (user: User) => {
        setGeneratingResetId(user.id);
        router.post(route('admin.users.reset-link', user.id), undefined, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);
                } else if (flash?.reset_link) {
                    setResetLink(flash.reset_link);
                } else {
                    toast.error('Impossible de générer le lien.');
                }
            },
            onFinish: () => setGeneratingResetId(null),
        });
    };

    const handleDelete = (user: User) => {
        if (
            confirm(
                `Supprimer définitivement ${user.first_name} ${user.last_name} ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('admin.users.destroy', user.id), {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    if (flash?.error) {
                        toast.error(flash.error);
                    } else if (flash?.success) {
                        toast.success(flash.success);
                    }
                },
                onError: () => {
                    toast.error(
                        'Une erreur est survenue lors de la suppression.',
                    );
                },
            });
        }
    };

    const activeRole = filters.role ?? null;
    const activeRequestStatus =
        (filters.professional_request_status as ProRequestStatus | undefined) ||
        null;
    const pendingActive = activeRequestStatus === 'pending';
    const tabs: { value: string | null; label: string; count: number }[] = [
        { value: null, label: 'Tous', count: totalUsers },
        { value: 'seller', label: 'Vendeurs', count: roleCounts.seller },
        { value: 'buyer', label: 'Acheteurs', count: roleCounts.buyer },
        {
            value: 'professional_buyer',
            label: 'Acheteurs pro',
            count: roleCounts.professional_buyer,
        },
    ];

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Utilisateurs
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        {totalUsers} utilisateur
                        {totalUsers > 1 ? 's' : ''} au total.
                    </p>
                </div>
                <Link
                    href={route('admin.users.create')}
                    className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                >
                    <Users className="h-4 w-4" />
                    Nouvel utilisateur
                </Link>
            </header>

            {/*
                Trade-request queue. Not a role tab: a buyer waiting on a
                decision is still `buyer`, so this is its own filter and its
                own banner — and it only exists while there's work to do.
            */}
            {pendingProfessionalCount > 0 && (
                <button
                    type="button"
                    onClick={() =>
                        setRequestStatus(pendingActive ? null : 'pending')
                    }
                    className={`flex items-center gap-3 rounded-xl border px-5 py-3.5 text-left transition ${
                        pendingActive
                            ? 'border-amber-400 bg-amber-100'
                            : 'border-amber-300/60 bg-amber-50 hover:bg-amber-100'
                    }`}
                >
                    <Clock className="h-5 w-5 shrink-0 text-amber-600" />
                    <span className="text-sm text-ink">
                        <span className="font-medium">
                            {pendingProfessionalCount} demande
                            {pendingProfessionalCount > 1 ? 's' : ''} de compte
                            professionnel
                        </span>{' '}
                        <span className="text-ink-soft">
                            {pendingActive
                                ? '— cliquez pour afficher tout le monde'
                                : 'en attente de votre décision.'}
                        </span>
                    </span>
                </button>
            )}

            {/* ── Role filter tabs ─────────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = activeRole === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setRole(tab.value)}
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

            {/*
                Trade-request status filter. Separate from the role tabs
                above for the same reason as the banner: the status
                (pending/approved/declined) and the role diverge — a
                declined request leaves the account a plain buyer, and an
                approved one shows both `professional_buyer` above *and*
                `approved` here.
            */}
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium text-ink-soft">
                    Demandes pro :
                </span>
                {(
                    [
                        { value: null, label: 'Toutes' },
                        {
                            value: 'pending' as const,
                            label: 'En attente',
                        },
                        {
                            value: 'approved' as const,
                            label: 'Approuvées',
                        },
                        {
                            value: 'declined' as const,
                            label: 'Refusées',
                        },
                    ] satisfies {
                        value: ProRequestStatus | null;
                        label: string;
                    }[]
                ).map((tab) => {
                    const active = activeRequestStatus === tab.value;
                    const count =
                        tab.value === null
                            ? professionalRequestCounts.pending +
                              professionalRequestCounts.approved +
                              professionalRequestCounts.declined
                            : professionalRequestCounts[tab.value];

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setRequestStatus(tab.value)}
                            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                                active
                                    ? 'border-ink bg-ink text-white'
                                    : 'border-border bg-card text-ink-soft hover:border-ink/30 hover:text-ink'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-[10px] ${
                                    active
                                        ? 'bg-white/20 text-white'
                                        : 'bg-muted text-ink-soft'
                                }`}
                            >
                                {count}
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
                    placeholder="Nom, téléphone, ou boutique…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Nom</TableHead>
                            <TableHead>Email &amp; téléphone</TableHead>
                            <TableHead>Rôle</TableHead>
                            <TableHead>Boutique</TableHead>
                            <TableHead>Inscription</TableHead>
                            <TableHead className="text-right">
                                Produits
                            </TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="h-32 text-center text-ink-soft"
                                >
                                    Aucun utilisateur ne correspond à cette
                                    recherche.
                                </TableCell>
                            </TableRow>
                        ) : (
                            users.data.map((user) => (
                                <TableRow
                                    key={user.id}
                                    className={
                                        user.is_banned ? 'bg-red-50/40' : ''
                                    }
                                >
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route(
                                                    'admin.users.show',
                                                    user.id,
                                                )}
                                                className="font-medium text-ink hover:underline"
                                            >
                                                {user.first_name}{' '}
                                                {user.last_name}
                                            </Link>
                                            {user.is_banned ? (
                                                <Badge
                                                    variant="destructive"
                                                    className="text-[10px]"
                                                >
                                                    Bloqué
                                                </Badge>
                                            ) : (
                                                <></>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col text-sm">
                                            <span className="text-ink">
                                                {user.email || '—'}
                                            </span>
                                            <span className="text-ink-soft">
                                                {user.phone_number || '—'}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${ROLE_BADGE[user.role]}`}
                                            >
                                                {ROLE_LABEL[user.role]}
                                            </span>
                                            <ProRequestStatusBadge
                                                user={user}
                                            />
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {user.store ? (
                                            <div className="flex flex-col text-sm">
                                                <span className="font-medium text-ink">
                                                    {user.store.store_name}
                                                </span>
                                                <span className="text-xs text-ink-soft">
                                                    {
                                                        STORE_TYPE_LABEL[
                                                            user.store.type
                                                        ]
                                                    }
                                                    {user.store.city &&
                                                        ` · ${user.store.city.name}`}
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-sm text-ink-soft/60">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm text-ink-soft">
                                        {formatDate(user.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {user.role === 'seller' ? (
                                            <span className="inline-flex items-center gap-1.5 text-sm text-ink">
                                                <Package className="h-3.5 w-3.5 text-ink-soft" />
                                                {user.products_count}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-ink-soft/60">
                                                —
                                            </span>
                                        )}
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
                                                            'admin.users.show',
                                                            user.id,
                                                        )}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                        Voir le profil
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={route(
                                                            'admin.users.edit',
                                                            user.id,
                                                        )}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                        Modifier
                                                    </Link>
                                                </DropdownMenuItem>
                                                {user.role === 'seller' &&
                                                    user.store && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'admin.products.create',
                                                                    {
                                                                        store_id:
                                                                            user
                                                                                .store
                                                                                .id,
                                                                    },
                                                                )}
                                                                className="flex items-center gap-2"
                                                            >
                                                                <Package className="h-4 w-4" />
                                                                Ajouter un
                                                                produit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                <DropdownMenuItem
                                                    disabled={
                                                        generatingResetId ===
                                                        user.id
                                                    }
                                                    onClick={() =>
                                                        handleGenerateResetLink(
                                                            user,
                                                        )
                                                    }
                                                    className="flex items-center gap-2"
                                                >
                                                    <KeyRound className="h-4 w-4" />
                                                    Copier un lien de
                                                    réinitialisation
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        handleBan(user)
                                                    }
                                                    className="flex items-center gap-2"
                                                >
                                                    {user.is_banned ? (
                                                        <>
                                                            <UserCheck className="h-4 w-4" />
                                                            Débloquer
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Ban className="h-4 w-4" />
                                                            Bloquer
                                                        </>
                                                    )}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        handleDelete(user)
                                                    }
                                                    className="flex items-center gap-2 text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Supprimer
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* ── Pagination ───────────────────────────── */}
                {users.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {users.from}–{users.to} sur {users.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!users.prev_page_url}
                                onClick={() =>
                                    router.get(users.prev_page_url!, filters, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            {users.links
                                .filter((link) => !isNaN(Number(link.label)))
                                .map((link) => (
                                    <Button
                                        key={link.label}
                                        variant={
                                            link.active ? 'default' : 'ghost'
                                        }
                                        size="sm"
                                        className="h-8 w-8"
                                        onClick={() =>
                                            link.url &&
                                            router.get(link.url, filters, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {link.label}
                                    </Button>
                                ))}
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                disabled={!users.next_page_url}
                                onClick={() =>
                                    router.get(users.next_page_url!, filters, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResetLinkDialog
                link={resetLink}
                onClose={() => setResetLink(null)}
            />
        </div>
    );
}

UsersIndex.layout = (page: ReactNode) => <AdminLayout children={page} />;
