import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Ban,
    EllipsisVertical,
    Eye,
    Hourglass,
    KeyRound,
    Loader2,
    Package,
    Pencil,
    Search,
    ShieldCheck,
    Trash2,
    UserCheck,
    Mail,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ExportMenu from '@/components/admin/export-menu';
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
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import { SOCIAL_MARKS } from '@/pages/auth/login/SocialButtons';
import type { SocialProvider } from '@/pages/auth/login/SocialButtons';
import type {
    PageProps,
    PaginatedData,
    ResetLinkPayload,
    StoreType,
    User,
    UserRole,
} from '@/types';

type SignupMethod = 'admin' | 'email' | SocialProvider;

type UserRow = User & {
    products_count: number;
    /** The admin who opened the account; null when they signed up themselves. */
    created_by: number | null;
    creator: {
        id: number;
        first_name: string | null;
        last_name: string | null;
    } | null;
    /** How the account was opened — AdminUserListService::signupMethod(). */
    signup_method: SignupMethod;
    /** Social identities the person can sign in with, whenever linked. */
    linked_providers: SocialProvider[];
    store: {
        id: number;
        store_name: string;
        type: StoreType;
        city: { name: string } | null;
        /** Approved is what shows the public "Vérifié par Hirfaty" mark. */
        verification_status: 'pending' | 'approved' | 'rejected';
    } | null;
};

interface Props {
    users: PaginatedData<UserRow>;
    totalUsers: number;
    roleCounts: Record<'buyer' | 'professional_buyer' | 'seller', number>;
    /** Signups stopped before the password step — see pending.tsx. */
    pendingSignupCount: number;
    filters: {
        search?: string;
        role?: string;
    };
}

const PROVIDER_LABEL: Record<SocialProvider, string> = {
    google: 'Google',
    facebook: 'Facebook',
};

/** Brand mark shrunk to badge size — the marks ship at 18px. */
function ProviderMark({ provider }: { provider: SocialProvider }) {
    return (
        <span className="inline-flex [&_svg]:h-3 [&_svg]:w-3">
            {SOCIAL_MARKS[provider]}
        </span>
    );
}

/**
 * How the account was opened: by the desk (an artisan signed up in person),
 * or by the person themselves — with an email code, Google, or Facebook.
 * Social identities linked after the fact are listed underneath.
 */
function SignupMethodBadge({ user }: { user: UserRow }) {
    const linkedLater = user.linked_providers.filter(
        (provider) => provider !== user.signup_method,
    );

    return (
        <>
            <SignupBadge user={user} />
            {linkedLater.map((provider) => (
                <span
                    key={provider}
                    className="inline-flex items-center gap-1 text-[11px] text-ink-soft"
                    title={`Compte ${PROVIDER_LABEL[provider]} lié après l'inscription`}
                >
                    <ProviderMark provider={provider} />
                    {PROVIDER_LABEL[provider]} lié
                </span>
            ))}
        </>
    );
}

function SignupBadge({ user }: { user: UserRow }) {
    const method = user.signup_method;

    if (method === 'google' || method === 'facebook') {
        return (
            <span
                className="inline-flex items-center gap-1 rounded-full border border-border bg-white px-2 py-0.5 text-[11px] font-medium text-ink"
                title={`Inscrit avec ${PROVIDER_LABEL[method]}`}
            >
                <ProviderMark provider={method} />
                {PROVIDER_LABEL[method]}
            </span>
        );
    }

    if (method === 'email') {
        return (
            <span
                className="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-medium text-sky-700"
                title="Inscrit avec son adresse email"
            >
                <Mail className="h-3 w-3" />
                Email
            </span>
        );
    }

    const adminName = [user.creator?.first_name, user.creator?.last_name]
        .filter(Boolean)
        .join(' ');

    return (
        <span
            className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700"
            title={
                adminName
                    ? `Compte créé par ${adminName}`
                    : "Compte créé par l'administration"
            }
        >
            <ShieldCheck className="h-3 w-3" />
            Créé par l'admin
        </span>
    );
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
    pendingSignupCount,
    filters,
}: Props) {
    const { t } = useTranslation();
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
            <Head title={t('titles.admin.users')} />
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
                <div className="flex flex-wrap items-center gap-2">
                    <ExportMenu
                        routeName="admin.users.export"
                        filters={filters}
                    />
                    <Link
                        href={route('admin.users.create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                    >
                        <Users className="h-4 w-4" />
                        Nouvel utilisateur
                    </Link>
                </div>
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

                {/*
                    Not a role: these people have no account yet (the
                    `users` row is only written at the password step), so
                    this leads to their own page rather than filtering this
                    table.
                */}
                <Link
                    href={route('admin.users.pending')}
                    className="inline-flex items-center gap-2 rounded-lg border border-dashed border-border bg-card px-3.5 py-2 text-sm font-medium text-ink-soft transition hover:border-ink/30 hover:text-ink"
                >
                    <Hourglass className="h-3.5 w-3.5" />
                    Inscriptions en cours
                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-xs text-ink-soft">
                        {pendingSignupCount}
                    </span>
                </Link>
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
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {user.store ? (
                                            <div className="flex flex-col text-sm">
                                                <span className="flex items-center gap-1.5 font-medium text-ink">
                                                    {user.store.store_name}
                                                    {/* Same approval that puts
                                                        "Vérifié par Hirfaty" on
                                                        the storefront. */}
                                                    {user.store
                                                        .verification_status ===
                                                        'approved' && (
                                                        <span
                                                            role="img"
                                                            aria-label="Vérifié par Hirfaty"
                                                            title="Vérifié par Hirfaty"
                                                            className="inline-flex shrink-0 text-souk-green"
                                                        >
                                                            <BadgeCheck className="h-3.5 w-3.5" />
                                                        </span>
                                                    )}
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
                                    <TableCell>
                                        <div className="flex flex-col items-start gap-1">
                                            <span className="text-sm text-ink-soft">
                                                {formatDate(user.created_at)}
                                            </span>
                                            <SignupMethodBadge user={user} />
                                        </div>
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
                            {users.links.map((link, i) => (
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

            <ResetLinkDialog
                link={resetLink}
                onClose={() => setResetLink(null)}
            />
        </div>
    );
}

UsersIndex.layout = (page: ReactNode) => <AdminLayout children={page} />;
