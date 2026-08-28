import { Link, router } from '@inertiajs/react';
import {
    Ban,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    PackagePlus,
    Plus,
    Search,
    Trash2,
    UserCheck,
    // Users, UserPlus — re-add these when the stat cards below are restored.
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import CopyInviteLinkButton from '@/components/admin/copy-invite-link-button';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import type { PaginatedData, User } from '@/types';

/** The list eager-loads each seller's store so we can link to "add a product for them". */
type UserRow = User & { store?: { id: number } | null };

interface Props {
    users: PaginatedData<UserRow>;
    totalUsers: number;
    newUsersThisWeek: number;
    filters: {
        search?: string;
        role?: string;
        banned?: string;
    };
}

const roleLabels: Record<string, string> = {
    buyer: 'Acheteur',
    seller: 'Vendeur',
    super_admin: 'Administrateur',
};

/** Soft-tinted role pills, in the brand palette rather than default shadcn variants. */
const rolePill: Record<string, string> = {
    buyer: 'bg-ink/[0.06] text-ink',
    seller: 'bg-terracotta/10 text-terracotta-deep',
    super_admin: 'bg-olive/10 text-olive',
};

const roleDot: Record<string, string> = {
    buyer: 'bg-ink/50',
    seller: 'bg-terracotta',
    super_admin: 'bg-olive',
};

const roleAvatar: Record<string, string> = {
    buyer: 'bg-ink text-cream',
    seller: 'bg-terracotta text-cream',
    super_admin: 'bg-olive text-cream',
};

function initials(user: User) {
    return `${user.first_name?.[0] ?? ''}${user.last_name?.[0] ?? ''}`.toUpperCase();
}

export default function UsersIndex({
    users,
    // totalUsers, newUsersThisWeek — still sent by the controller, surfaced
    // again when the stat cards below are restored.
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    route('admin.users.index'),
                    { ...filters, search, page: 1 },
                    { preserveState: true, replace: true },
                );
            }
        }, 500);

        return () => clearTimeout(timer);
    }, [search]);

    const handleRoleChange = (role: string) => {
        router.get(
            route('admin.users.index'),
            { ...filters, role: role === 'all' ? undefined : role, page: 1 },
            { preserveState: true },
        );
    };

    const handleBan = (user: UserRow) => {
        if (
            confirm(
                `Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} cet utilisateur ?`,
            )
        ) {
            router.patch(route('admin.users.ban', user.id));
        }
    };

    const handleDelete = (user: UserRow) => {
        if (
            confirm(
                'Voulez-vous vraiment supprimer cet utilisateur ? Cette action est irréversible.',
            )
        ) {
            router.delete(route('admin.users.destroy', user.id));
        }
    };

    return (
        <div className="flex flex-col gap-8 p-6 md:p-8">
            <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 className="h-editorial text-4xl text-ink md:text-5xl">
                        Utilisateurs
                    </h1>
                    <p className="mt-2 text-ink-soft">
                        Gérez les membres de la plateforme et leurs accès.
                    </p>
                </div>
                <Button asChild className="w-fit gap-2">
                    <Link href={route('admin.users.create')}>
                        <Plus className="h-4 w-4" />
                        Nouvel utilisateur
                    </Link>
                </Button>
            </header>

            {/*
                Stat cards (Total utilisateurs / Nouveaux 7j) temporarily hidden.
                Re-add the `Users` and `UserPlus` icon imports and the
                `totalUsers` / `newUsersThisWeek` props above to restore.

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="flex items-center gap-4 rounded-lg border border-ink/10 bg-paper p-6">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-cream">
                            <Users className="h-5 w-5 text-ink" />
                        </div>
                        <div>
                            <p className="eyebrow text-xs">Total Utilisateurs</p>
                            <p className="font-display text-2xl text-ink">{totalUsers}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4 rounded-lg border border-ink/10 bg-paper p-6">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-terracotta/10">
                            <UserPlus className="h-5 w-5 text-terracotta" />
                        </div>
                        <div>
                            <p className="eyebrow text-xs">Nouveaux (7j)</p>
                            <p className="font-display text-2xl text-ink">{newUsersThisWeek}</p>
                        </div>
                    </div>
                </div>
            */}

            {/* Everything — toolbar, table, pagination — lives in one card. */}
            <div className="overflow-hidden rounded-xl border border-ink/10 bg-paper shadow-[0_1px_2px_rgba(28,33,40,0.04)]">
                {/* Toolbar */}
                <div className="flex flex-col gap-4 border-b border-ink/10 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-baseline gap-2.5">
                        <h2 className="font-display text-lg text-ink">
                            Tous les utilisateurs
                        </h2>
                        <span className="rounded-full bg-ink/[0.06] px-2 py-0.5 text-xs font-medium text-ink-soft">
                            {users.total}
                        </span>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative sm:w-72">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                            <Input
                                placeholder="Rechercher un utilisateur..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="h-9 border-ink/10 bg-cream/40 pl-9"
                            />
                        </div>

                        <Select
                            value={filters.role || 'all'}
                            onValueChange={handleRoleChange}
                        >
                            <SelectTrigger className="h-9 w-full border-ink/10 bg-cream/40 sm:w-[160px]">
                                <SelectValue placeholder="Rôle" />
                            </SelectTrigger>
                            <SelectContent className="border-ink/10">
                                <SelectItem value="all">
                                    Tous les rôles
                                </SelectItem>
                                <SelectItem value="buyer">Acheteurs</SelectItem>
                                <SelectItem value="seller">Vendeurs</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Table */}
                <Table>
                    <TableHeader>
                        <TableRow className="border-ink/10 bg-cream/40 hover:bg-cream/40">
                            <TableHead className="h-11 px-5 text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Utilisateur
                            </TableHead>
                            <TableHead className="h-11 text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Téléphone
                            </TableHead>
                            <TableHead className="h-11 text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Rôle
                            </TableHead>
                            <TableHead className="h-11 text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Statut
                            </TableHead>
                            <TableHead className="h-11 text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Inscription
                            </TableHead>
                            <TableHead className="h-11 px-5 text-right text-[11px] font-semibold tracking-[0.12em] text-ink-soft uppercase">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="h-32 text-center text-ink-soft"
                                >
                                    Aucun utilisateur trouvé.
                                </TableCell>
                            </TableRow>
                        ) : (
                            users.data.map((user) => (
                                <TableRow
                                    key={user.id}
                                    className="border-ink/[0.07] transition-colors duration-200 hover:bg-cream/50"
                                >
                                    {/* Identity — avatar + name + email stacked */}
                                    <TableCell className="px-5 py-3.5">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-display text-xs ${roleAvatar[user.role as string] || 'bg-ink-soft text-cream'}`}
                                            >
                                                {initials(user)}
                                            </div>
                                            <div className="flex min-w-0 flex-col">
                                                <span className="truncate font-medium text-ink">
                                                    {user.first_name}{' '}
                                                    {user.last_name}
                                                </span>
                                                <span className="truncate text-xs text-ink-soft">
                                                    {user.email}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell className="text-sm text-ink-soft">
                                        {user.phone_number || '—'}
                                    </TableCell>

                                    <TableCell>
                                        <span
                                            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${rolePill[user.role as string] || 'bg-ink/[0.06] text-ink-soft'}`}
                                        >
                                            <span
                                                className={`h-1.5 w-1.5 rounded-full ${roleDot[user.role as string] || 'bg-ink/40'}`}
                                            />
                                            {roleLabels[user.role as string] ||
                                                (user.role as string)}
                                        </span>
                                    </TableCell>

                                    <TableCell>
                                        {user.is_banned ? (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive">
                                                <span className="h-1.5 w-1.5 rounded-full bg-destructive" />
                                                Bloqué
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-olive/10 px-2.5 py-1 text-xs font-medium text-olive">
                                                <span className="h-1.5 w-1.5 rounded-full bg-olive" />
                                                Actif
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className="text-sm text-ink-soft">
                                        {formatDate(
                                            user.created_at || user.updated_at,
                                        )}
                                    </TableCell>

                                    <TableCell className="px-5 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {user.role === 'seller' &&
                                                user.store && (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        title="Ajouter un produit pour ce vendeur"
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
                                                        >
                                                            <PackagePlus className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                            <CopyInviteLinkButton
                                                userId={user.id}
                                            />
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
                                                <DropdownMenuContent
                                                    align="end"
                                                    className="border-ink/10"
                                                >
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={route(
                                                                'admin.users.show',
                                                                user.id,
                                                            )}
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Eye className="h-4 w-4" />{' '}
                                                            Voir le profil
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            handleBan(user)
                                                        }
                                                        className="flex items-center gap-2 text-terracotta"
                                                    >
                                                        {user.is_banned ? (
                                                            <>
                                                                <UserCheck className="h-4 w-4" />{' '}
                                                                Débloquer
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Ban className="h-4 w-4" />{' '}
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
                                                        <Trash2 className="h-4 w-4" />{' '}
                                                        Supprimer
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* Pagination footer */}
                {users.last_page > 1 && (
                    <div className="flex flex-col gap-3 border-t border-ink/10 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs text-ink-soft">
                            Affichage de{' '}
                            <span className="font-medium text-ink">
                                {users.from}–{users.to}
                            </span>{' '}
                            sur{' '}
                            <span className="font-medium text-ink">
                                {users.total}
                            </span>{' '}
                            utilisateurs
                        </p>
                        <div className="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 border-ink/10"
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
                                        className={`h-8 w-8 ${link.active ? 'bg-ink text-cream' : 'text-ink-soft'}`}
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
                                className="h-8 w-8 border-ink/10"
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
