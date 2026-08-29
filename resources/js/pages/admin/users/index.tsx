import { Link, router } from '@inertiajs/react';
import {
    Ban,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Loader2,
    Package,
    Search,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
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
import type { PaginatedData, StoreType, User, UserRole } from '@/types';

type UserRow = User & {
    products_count: number;
    store: {
        store_name: string;
        type: StoreType;
        city: { name: string } | null;
    } | null;
};

interface Props {
    users: PaginatedData<UserRow>;
    totalUsers: number;
    roleCounts: Record<'buyer' | 'professional_buyer' | 'seller', number>;
    filters: {
        search?: string;
        role?: string;
    };
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
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
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

    const handleDelete = (user: User) => {
        if (
            confirm(
                `Supprimer définitivement ${user.first_name} ${user.last_name} ? Cette action est irréversible.`,
            )
        ) {
            router.delete(route('admin.users.destroy', user.id), {
                preserveScroll: true,
            });
        }
    };

    const activeRole = filters.role ?? null;
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
                                            <span className="font-medium text-ink">
                                                {user.first_name}{' '}
                                                {user.last_name}
                                            </span>
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
                                                {user.email}
                                            </span>
                                            <span className="text-ink-soft">
                                                {user.phone_number || '—'}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${ROLE_BADGE[user.role]}`}
                                        >
                                            {ROLE_LABEL[user.role]}
                                        </span>
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
        </div>
    );
}

UsersIndex.layout = (page: ReactNode) => <AdminLayout children={page} />;
