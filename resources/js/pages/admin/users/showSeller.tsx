import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Ban,
    Calendar,
    CheckCircle2,
    Clock,
    EllipsisVertical,
    ExternalLink,
    Eye,
    KeyRound,
    Mail,
    MapPin,
    Package,
    Pencil,
    Phone,
    ShoppingBag,
    Store,
    Trash2,
    UserCheck,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ResetLinkDialog from '@/components/admin/reset-link-dialog';
import StagingWarning from '@/components/admin/staging-warning';
import UserNotesCard from '@/components/admin/user-notes-card';
import type { AdminUserNote } from '@/components/admin/user-notes-card';
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
import { frenchName } from '@/lib/category';
import { formatDate } from '@/lib/utils';
import type {
    Category,
    Order,
    PageProps,
    Product,
    ResetLinkPayload,
    StoreType,
    User,
} from '@/types';

/** Labels for the seller-type badge shown on the store card. */
const STORE_TYPE_LABEL: Record<StoreType, string> = {
    artist: 'Artiste',
    company: 'Entreprise',
    cooperative: 'Coopérative',
};

/**
 * Order money for this shop. Replaced an earnings summary (net / available /
 * pending off the `earnings` ledger) — what the artisan is owed after
 * commission is not reported anywhere for now. See the disabled
 * "Contrat & commission" card further down, which holds its place.
 */
export type SalesSummary = {
    delivered_total: number;
    in_progress_total: number;
};

export type OrderStats = {
    total: number;
    pending: number;
    delivered: number;
    cancelled: number;
};

export type ProductStats = {
    total: number;
    published: number;
    pending_review: number;
    rejected: number;
};

interface Props {
    user: User & {
        store: {
            id: number;
            store_name: string;
            slug: string;
            description: string;
            type: StoreType;
            /**
             * The shop vetting an admin performs from /admin/stores. Approved
             * is what clears the shop to sell *and* what puts the public
             * "Vérifié par Hirfaty" mark on its storefront, so it is shown
             * and actioned here too rather than only on the stores table.
             */
            verification_status: 'pending' | 'approved' | 'rejected';
            rejection_reason: string | null;
            /** Required fields the seller still owes; blocks approval. */
            missing_fields: string[];
            logo: string | null;
            banner: string | null;
            city: { name: string };
            category: Category | null;
            // `order_items_count` decides whether Delete is offered: a product
            // that has ever been ordered can only be archived, never removed.
            products: (Product & { order_items_count?: number })[];
        };
    };
    sales: SalesSummary;
    orderStats: OrderStats;
    productStats: ProductStats;
    recentOrders: Order[];
    notes: AdminUserNote[];
}

const STATUS_BADGE: Record<string, string> = {
    pending_review: 'bg-amber-100 text-amber-700',
    published: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    archived: 'bg-slate-100 text-slate-700',
    draft: 'bg-slate-100 text-slate-700',
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    shipped: 'bg-violet-100 text-violet-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const formatMAD = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

/** Small bordered card, the base unit this whole page is built from. */
function Card({
    title,
    icon: Icon,
    action,
    children,
    className = '',
}: {
    title?: string;
    icon?: typeof Package;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`overflow-hidden rounded-xl border border-border bg-card shadow-sm ${className}`}
        >
            {title && (
                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    <div className="flex items-center gap-2">
                        {Icon && <Icon className="h-4 w-4 text-ink-soft" />}
                        <h2 className="text-sm font-semibold text-ink">
                            {title}
                        </h2>
                    </div>
                    {action}
                </div>
            )}
            {children}
        </div>
    );
}

/** One tile in the top KPI row. */
function Stat({
    label,
    value,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    icon: typeof Package;
    tone?: 'default' | 'positive' | 'warning';
}) {
    const toneClass = {
        default: 'text-ink',
        positive: 'text-emerald-700',
        warning: 'text-amber-700',
    }[tone];

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-medium text-ink-soft">
                <Icon className="h-3.5 w-3.5" />
                {label}
            </div>
            <p className={`mt-2 text-xl font-semibold ${toneClass}`}>{value}</p>
        </div>
    );
}

/**
 * Reserved space for the artisan's contract and Hirfaty's cut of it.
 *
 * Intentionally inert: nothing here is wired to state, a form or an endpoint,
 * and there is no contract/commission column on `stores` yet. It exists so the
 * shape of the feature is visible and its spot on the page is held while the
 * commercial terms are still being decided. Everything is rendered greyed and
 * `aria-hidden`/`disabled` so it can't be tabbed into or mistaken for live
 * data — swap the dashes for real values once the fields exist.
 */
function ContractPlaceholderCard() {
    const rows = [
        { label: 'Type de contrat', value: '—' },
        { label: 'Commission Hirfaty', value: '—' },
        { label: 'Montant dû à Hirfaty', value: '—' },
        { label: 'Reste à verser à l’artisan', value: '—' },
    ];

    return (
        <div className="overflow-hidden rounded-xl border border-dashed border-border bg-muted/30 shadow-sm">
            <div className="flex items-center justify-between gap-3 border-b border-dashed border-border px-5 py-3.5">
                <div className="flex items-center gap-2">
                    <Wallet className="h-4 w-4 text-ink-soft/60" />
                    <h2 className="text-sm font-semibold text-ink-soft/80">
                        Contrat &amp; commission
                    </h2>
                </div>
                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-[11px] font-medium text-ink-soft/70">
                    Bientôt
                </span>
            </div>

            <div className="space-y-3 p-5 select-none" aria-hidden="true">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-xs text-ink-soft/70">
                            {row.label}
                        </span>
                        <span className="rounded bg-muted px-2 py-0.5 text-xs font-medium text-ink-soft/50 tabular-nums">
                            {row.value}
                        </span>
                    </div>
                ))}

                <button
                    type="button"
                    disabled
                    className="mt-1 w-full cursor-not-allowed rounded-lg border border-border bg-muted/50 px-4 py-2.5 text-xs font-medium text-ink-soft/50"
                >
                    Définir le contrat
                </button>
            </div>
        </div>
    );
}

const ShowSeller = ({
    user,
    sales,
    orderStats,
    productStats,
    recentOrders,
    notes,
}: Props) => {
    const { t } = useTranslation();
    const [generatingReset, setGeneratingReset] = useState(false);
    const [resetLink, setResetLink] = useState<ResetLinkPayload | null>(null);
    /** The product awaiting delete confirmation, or null when none is. */
    const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [verifying, setVerifying] = useState(false);

    /**
     * Grant the badge. Same endpoint as the stores table, so the seller gets
     * the StoreApproved email from StoreVerificationService either way.
     */
    const handleVerify = () => {
        if (!user.store) {
            return;
        }

        setVerifying(true);
        router.patch(
            route('admin.stores.approve', user.store.id),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    if (flash?.error) {
                        toast.error(flash.error);

                        return;
                    }

                    toast.success(
                        flash?.success ??
                            'Atelier vérifié. Le vendeur a été prévenu.',
                    );
                },
                onError: () => toast.error('La vérification a échoué.'),
                onFinish: () => setVerifying(false),
            },
        );
    };

    /**
     * Withdraw the badge. The reject endpoint requires a reason of at least
     * ten characters — it is mailed to the seller as their only explanation —
     * so this prompts rather than sending a blank one.
     */
    const handleUnverify = () => {
        if (!user.store) {
            return;
        }

        const reason = window.prompt(
            'Pourquoi retirez-vous la vérification ? (10 caractères minimum — ce message est envoyé au vendeur)',
        );

        if (reason === null) {
            return;
        }

        if (reason.trim().length < 10) {
            toast.error('Le motif doit faire au moins 10 caractères.');

            return;
        }

        setVerifying(true);
        router.patch(
            route('admin.stores.reject', user.store.id),
            { reason },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;

                    toast.success(
                        flash?.success ??
                            'Vérification retirée. Le vendeur a été prévenu.',
                    );
                },
                onError: (errs) =>
                    toast.error(
                        errs.reason ??
                            'Le retrait de la vérification a échoué.',
                    ),
                onFinish: () => setVerifying(false),
            },
        );
    };

    const confirmDeleteProduct = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.products.destroy', deleteTarget.id), {
            preserveScroll: true,
            /*
             * The endpoint replies 200 whether it deleted or refused — a
             * product with order history is blocked, and that outcome only
             * shows up in `flash`. Reading it here rather than assuming
             * success is what stops a refusal reporting as "deleted".
             */
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);

                    return;
                }

                toast.success(flash?.success ?? 'Produit supprimé.');
            },
            onError: () =>
                toast.error('Une erreur est survenue lors de la suppression.'),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const handleBan = () => {
        if (
            confirm(
                `Voulez-vous ${user.is_banned ? 'débloquer' : 'bloquer'} cet utilisateur ?`,
            )
        ) {
            router.patch(route('admin.users.ban', user.id));
        }
    };

    const handleGenerateResetLink = () => {
        setGeneratingReset(true);
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
            onFinish: () => setGeneratingReset(false),
        });
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head
                title={
                    `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() ||
                    t('titles.admin.users')
                }
            />
            <StagingWarning path="/admin/users" />
            {/* ── Header ───────────────────────────────────── */}
            <div>
                <Link
                    href={route('admin.users.index')}
                    className="mb-3 inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" /> Retour aux utilisateurs
                </Link>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                                {user.first_name} {user.last_name}
                            </h1>
                            <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                Vendeur
                            </span>
                            {user.is_banned ? (
                                <Badge
                                    variant="destructive"
                                    className="rounded-full"
                                >
                                    Bloqué
                                </Badge>
                            ) : (
                                <></>
                            )}
                            {/* The same approval that drives the public
                                "Vérifié par Hirfaty" mark on the storefront. */}
                            {user.store?.verification_status === 'approved' && (
                                <span
                                    title="Cet atelier porte la mention « Vérifié par Hirfaty » sur la boutique."
                                    className="inline-flex items-center gap-1 rounded-full bg-souk-green/10 px-2.5 py-1 text-xs font-medium text-souk-green"
                                >
                                    <BadgeCheck className="h-3.5 w-3.5" />
                                    Vérifié
                                </span>
                            )}
                            {user.store?.verification_status === 'pending' && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">
                                    <Clock className="h-3.5 w-3.5" />
                                    En attente de validation
                                </span>
                            )}
                            {user.store?.verification_status === 'rejected' && (
                                <span
                                    title={
                                        user.store.rejection_reason ?? undefined
                                    }
                                    className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700"
                                >
                                    <Ban className="h-3.5 w-3.5" />
                                    Refusé
                                </span>
                            )}
                        </div>
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-ink-soft">
                            <Calendar className="h-3.5 w-3.5" />
                            Membre depuis le {formatDate(user.created_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {/*
                         * Verify / un-verify, hitting the same endpoints the
                         * stores table uses (StoreVerificationService), so the
                         * seller gets the same StoreApproved / StoreRejected
                         * email either way.
                         *
                         * Approving is hidden while the shop is still missing
                         * required fields: the endpoint refuses those, and a
                         * button guaranteed to fail reads as broken.
                         */}
                        {user.store?.verification_status !== 'approved' &&
                            (user.store?.missing_fields?.length ?? 0) === 0 && (
                                <Button
                                    className="rounded-lg"
                                    disabled={verifying}
                                    onClick={handleVerify}
                                >
                                    <BadgeCheck className="h-4 w-4" />
                                    Marquer comme vérifié
                                </Button>
                            )}
                        {user.store?.verification_status !== 'approved' &&
                            (user.store?.missing_fields?.length ?? 0) > 0 && (
                                <span className="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-ink-soft">
                                    Profil incomplet — manque&nbsp;:{' '}
                                    {user.store.missing_fields.join(', ')}
                                </span>
                            )}
                        {user.store?.verification_status === 'approved' && (
                            <Button
                                variant="outline"
                                className="rounded-lg"
                                disabled={verifying}
                                onClick={handleUnverify}
                            >
                                <Ban className="h-4 w-4" />
                                Retirer la vérification
                            </Button>
                        )}

                        <Link
                            href={route('admin.products.create', {
                                store_id: user.store.id,
                            })}
                            className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90"
                        >
                            <Package className="h-4 w-4" />
                            Ajouter un produit
                        </Link>
                        <Button
                            variant="outline"
                            className="rounded-lg"
                            disabled={generatingReset}
                            onClick={handleGenerateResetLink}
                        >
                            <KeyRound className="h-4 w-4" />
                            Copier un lien de réinitialisation
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-lg"
                        >
                            <Link href={route('admin.users.edit', user.id)}>
                                <Pencil className="h-4 w-4" /> Modifier
                            </Link>
                        </Button>
                        <Button
                            variant={user.is_banned ? 'outline' : 'destructive'}
                            className="rounded-lg"
                            onClick={handleBan}
                        >
                            {user.is_banned ? (
                                <>
                                    <UserCheck className="h-4 w-4" /> Débloquer
                                </>
                            ) : (
                                <>
                                    <Ban className="h-4 w-4" /> Bloquer
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </div>

            {/* ── KPI row ──────────────────────────────────── */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Stat
                    label="Ventes livrées"
                    value={formatMAD(sales.delivered_total)}
                    icon={CheckCircle2}
                    tone="positive"
                />
                <Stat
                    label="Commandes en cours"
                    value={formatMAD(sales.in_progress_total)}
                    icon={Clock}
                    tone="warning"
                />
                <Stat
                    label="Produits en ligne"
                    value={productStats.published}
                    icon={Package}
                />
                <Stat
                    label="Commandes livrées"
                    value={`${orderStats.delivered}/${orderStats.total}`}
                    icon={ShoppingBag}
                />
            </div>

            {/* ── Body ─────────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Sidebar */}
                <aside className="space-y-6 lg:col-span-4">
                    <Card title="Contact">
                        <div className="space-y-4 p-5 text-sm">
                            <div className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                <div className="min-w-0">
                                    <p className="text-xs text-ink-soft">
                                        Email
                                    </p>
                                    <p className="truncate text-ink">
                                        {user.email || '—'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                <div>
                                    <p className="text-xs text-ink-soft">
                                        Téléphone
                                    </p>
                                    <p className="text-ink">
                                        {user.phone_number || 'N/A'}
                                    </p>
                                </div>
                            </div>
                            {user.store?.city && (
                                <div className="flex items-start gap-3">
                                    <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-ink-soft" />
                                    <div>
                                        <p className="text-xs text-ink-soft">
                                            Ville
                                        </p>
                                        <p className="text-ink">
                                            {user.store.city.name}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </Card>

                    <UserNotesCard userId={user.id} notes={notes} />

                    <Card
                        title="Boutique"
                        icon={Store}
                        action={
                            /*
                             * The shop's public page. Used to point at
                             * /seller/{slug}, which is the artisan's own back
                             * office behind `role:seller` — so an admin
                             * following this link got a 403 rather than the
                             * shopfront they expected.
                             */
                            <Link
                                href={route(
                                    'stores.show',
                                    user.store?.slug ?? '',
                                )}
                                target="_blank"
                                className="inline-flex items-center gap-1 text-xs font-medium text-ink-soft transition hover:text-ink"
                            >
                                Voir en ligne
                                <ExternalLink className="h-3 w-3" />
                            </Link>
                        }
                    >
                        {/* Cover strip — omitted entirely when no banner was
                            uploaded, rather than showing an empty placeholder. */}
                        {user.store?.banner && (
                            <img
                                src={user.store.banner}
                                alt=""
                                className="h-24 w-full object-cover"
                            />
                        )}

                        <div className="space-y-4 p-5">
                            <div className="flex items-center gap-3">
                                {user.store?.logo ? (
                                    <img
                                        src={user.store.logo}
                                        alt=""
                                        className="h-10 w-10 shrink-0 rounded-full border border-border object-cover"
                                    />
                                ) : (
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                        <Store className="h-5 w-5 text-ink-soft" />
                                    </div>
                                )}
                                <p className="min-w-0 truncate text-sm font-semibold text-ink">
                                    {user.store?.store_name}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink-soft">
                                    {user.store?.category
                                        ? frenchName(user.store.category)
                                        : 'Artisanat'}
                                </span>
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink-soft">
                                    {user.store?.type
                                        ? STORE_TYPE_LABEL[user.store.type]
                                        : '—'}
                                </span>
                            </div>

                            <p className="text-sm leading-relaxed whitespace-pre-line text-ink-soft">
                                {user.store?.description ||
                                    'Aucune description fournie.'}
                            </p>
                        </div>
                    </Card>

                    <ContractPlaceholderCard />
                </aside>

                {/* Main column */}
                <div className="space-y-6 lg:col-span-8">
                    <Card title="Commandes récentes" icon={ShoppingBag}>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>ID</TableHead>
                                    <TableHead>Client</TableHead>
                                    <TableHead>Montant</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">
                                        Date
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentOrders.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-ink-soft"
                                        >
                                            Aucune commande enregistrée.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    recentOrders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs text-ink-soft">
                                                {order.reference}
                                            </TableCell>
                                            <TableCell className="text-sm text-ink">
                                                {order.buyer?.first_name}{' '}
                                                {order.buyer?.last_name}
                                            </TableCell>
                                            <TableCell className="text-sm font-medium text-ink">
                                                {formatMAD(
                                                    Number(order.total_amount),
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[order.status] ?? 'bg-muted text-ink-soft'}`}
                                                >
                                                    {order.status}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right text-xs text-ink-soft">
                                                {formatDate(order.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </Card>

                    <Card title="Produits" icon={Package}>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Produit</TableHead>
                                    <TableHead>Prix</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {(user.store?.products.length ?? 0) === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-ink-soft"
                                        >
                                            Aucun produit en ligne.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    user.store?.products.map((product) => {
                                        // Guaranteed to fail server-side, so
                                        // the action is disabled rather than
                                        // offered — see ProductDeletionService.
                                        const hasOrders =
                                            (product.order_items_count ?? 0) >
                                            0;

                                        return (
                                            <TableRow key={product.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <div className="h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
                                                            {product
                                                                .images?.[0] && (
                                                                <img
                                                                    src={
                                                                        product
                                                                            .images[0]
                                                                            .url
                                                                    }
                                                                    alt=""
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            )}
                                                        </div>
                                                        <Link
                                                            href={route(
                                                                'admin.products.show',
                                                                product.id,
                                                            )}
                                                            className="text-sm font-medium text-ink hover:underline"
                                                        >
                                                            {product.name}
                                                        </Link>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-sm text-ink">
                                                    {formatMAD(
                                                        Number(
                                                            product.sale_price ||
                                                                product.base_price,
                                                        ),
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm text-ink-soft">
                                                    {product.has_variants
                                                        ? 'Géré par déclinaison'
                                                        : `${product.stock} unités`}
                                                </TableCell>
                                                <TableCell>
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_BADGE[product.status] ?? 'bg-muted text-ink-soft'}`}
                                                    >
                                                        {product.status}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8"
                                                                aria-label={`Actions pour ${product.name}`}
                                                            >
                                                                <EllipsisVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={route(
                                                                        'admin.products.show',
                                                                        product.id,
                                                                    )}
                                                                    className="flex items-center gap-2"
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                    Voir le
                                                                    produit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={route(
                                                                        'admin.products.edit',
                                                                        product.id,
                                                                    )}
                                                                    className="flex items-center gap-2"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    Modifier
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                disabled={
                                                                    hasOrders
                                                                }
                                                                title={
                                                                    hasOrders
                                                                        ? 'Ce produit a des commandes — archivez-le plutôt'
                                                                        : undefined
                                                                }
                                                                onSelect={() =>
                                                                    setDeleteTarget(
                                                                        product,
                                                                    )
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
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </Card>
                </div>
            </div>

            {/* ── Delete confirmation ─────────────────────────── */}
            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Supprimer «&nbsp;{deleteTarget?.name}&nbsp;» ?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Cette action est irréversible : le produit, ses
                            photos et ses déclinaisons seront supprimés
                            définitivement.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDeleteProduct}
                            disabled={deleting}
                            className="bg-terracotta text-white hover:bg-terracotta/90"
                        >
                            {deleting ? 'Suppression…' : 'Supprimer'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <ResetLinkDialog
                link={resetLink}
                onClose={() => setResetLink(null)}
            />
        </div>
    );
};

ShowSeller.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default ShowSeller;
