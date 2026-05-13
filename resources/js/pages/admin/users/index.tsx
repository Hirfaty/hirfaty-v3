import { Link, router, usePage } from '@inertiajs/react';
import {
    Ban,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Search,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-react';
import type { ReactNode} from 'react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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

interface Props {
    users: PaginatedData<User>;
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

const roleVariants: Record<string, 'default' | 'secondary' | 'outline'> = {
    buyer: 'secondary',
    seller: 'default',
    super_admin: 'outline',
};

export default function UsersIndex({
    users,
    totalUsers,
    newUsersThisWeek,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const { auth } = usePage().props as any;

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

    const handleBan = (user: User) => {
        if (confirm(`Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} cet utilisateur ?`)) {
            router.patch(route('admin.users.ban', user.id));
        }
    };

    const handleDelete = (user: User) => {
        if (confirm('Voulez-vous vraiment supprimer cet utilisateur ? Cette action est irréversible.')) {
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
            </header>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="flex items-center gap-4 border border-ink/10 bg-paper p-6">
                    <div className="flex h-12 w-12 items-center justify-center bg-cream">
                        <Users className="h-6 w-6 text-ink" />
                    </div>
                    <div>
                        <p className="eyebrow text-xs">Total Utilisateurs</p>
                        <p className="font-display text-2xl text-ink">
                            {totalUsers}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-4 border border-ink/10 bg-paper p-6">
                    <div className="flex h-12 w-12 items-center justify-center bg-terracotta/10">
                        <Users className="h-6 w-6 text-terracotta" />
                    </div>
                    <div>
                        <p className="eyebrow text-xs">Nouveaux (7j)</p>
                        <p className="font-display text-2xl text-ink">
                            {newUsersThisWeek}
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div className="relative flex-1 max-w-md">
                    <Search className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-soft/50" />
                    <Input
                        placeholder="Rechercher par nom, email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="bg-paper"
                    />
                </div>

                <div className="flex items-center gap-3">
                    <Select
                        value={filters.role || 'all'}
                        onValueChange={handleRoleChange}
                    >
                        <SelectTrigger className="w-[180px] rounded-none border-ink/10 bg-paper font-display">
                            <SelectValue placeholder="Rôle" />
                        </SelectTrigger>
                        <SelectContent className="rounded-none border-ink/10">
                            <SelectItem value="all">Tous les rôles</SelectItem>
                            <SelectItem value="buyer">Acheteurs</SelectItem>
                            <SelectItem value="seller">Vendeurs</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="border border-ink/10 bg-paper">
                <Table>
                    <TableHeader className="bg-cream/50">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="font-display text-ink uppercase tracking-wider">Utilisateur</TableHead>
                            <TableHead className="font-display text-ink uppercase tracking-wider">Contact</TableHead>
                            <TableHead className="font-display text-ink uppercase tracking-wider">Rôle</TableHead>
                            <TableHead className="font-display text-ink uppercase tracking-wider">Date d'inscription</TableHead>
                            <TableHead className="text-right font-display text-ink uppercase tracking-wider">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-32 text-center text-ink-soft">
                                    Aucun utilisateur trouvé.
                                </TableCell>
                            </TableRow>
                        ) : (
                            users.data.map((user) => (
                                <TableRow key={user.id} className={user.is_banned ? 'opacity-60 bg-cream/20' : ''}>
                                    <TableCell>
                                        <div className="flex flex-col">
                                            <span className="font-display text-base text-ink">
                                                {user.first_name} {user.last_name}
                                            </span>
                                            {user.is_banned && (
                                                <span className="text-[10px] text-terracotta font-bold tracking-widest uppercase">
                                                    Bloqué
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col text-sm">
                                            <span className="text-ink">{user.email}</span>
                                            <span className="text-ink-soft">{user.phone_number || 'N/A'}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={roleVariants[user.role] || 'outline'} className="rounded-none">
                                            {roleLabels[user.role] || user.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-ink-soft">
                                        {formatDate(user.created_at || user.updated_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                                    <EllipsisVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="rounded-none border-ink/10">
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('admin.users.show', user.id)} className="flex items-center gap-2">
                                                        <Eye className="h-4 w-4" /> Voir le profil
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleBan(user)} className="flex items-center gap-2 text-terracotta">
                                                    {user.is_banned ? (
                                                        <>
                                                            <UserCheck className="h-4 w-4" /> Débloquer
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Ban className="h-4 w-4" /> Bloquer
                                                        </>
                                                    )}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDelete(user)} className="flex items-center gap-2 text-destructive">
                                                    <Trash2 className="h-4 w-4" /> Supprimer
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* Pagination */}
                {users.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-ink/10 p-6">
                        <p className="text-xs text-ink-soft">
                            Affichage de <span className="font-bold text-ink">{users.from}-{users.to}</span> sur <span className="font-bold text-ink">{users.total}</span> utilisateurs
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 rounded-none border-ink/10"
                                disabled={!users.prev_page_url}
                                onClick={() => router.get(users.prev_page_url!, filters, { preserveScroll: true })}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <div className="flex items-center gap-1">
                                {users.links.filter(link => !isNaN(Number(link.label))).map((link) => (
                                    <Button
                                        key={link.label}
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                        className={`h-8 w-8 rounded-none ${link.active ? 'bg-ink text-cream' : 'text-ink-soft'}`}
                                        onClick={() => link.url && router.get(link.url, filters, { preserveScroll: true })}
                                    >
                                        {link.label}
                                    </Button>
                                ))}
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8 rounded-none border-ink/10"
                                disabled={!users.next_page_url}
                                onClick={() => router.get(users.next_page_url!, filters, { preserveScroll: true })}
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
