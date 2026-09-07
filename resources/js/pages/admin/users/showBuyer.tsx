import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    KeyRound,
    Mail,
    Pencil,
    Phone,
    ShoppingBag,
    UserCheck,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ResetLinkDialog from '@/components/admin/reset-link-dialog';
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
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';
import type { Order, PageProps, ResetLinkPayload, Store, User } from '@/types';

interface Props {
    user: User & {
        orders: (Order & { store: Store })[];
    };
    orderStats: {
        total: number;
        pending: number;
        delivered: number;
        cancelled: number;
    };
    totalSpent: number;
}

const STATUS_BADGE: Record<string, string> = {
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
    children,
}: {
    title?: string;
    icon?: typeof ShoppingBag;
    children: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            {title && (
                <div className="flex items-center gap-2 border-b border-border px-5 py-3.5">
                    {Icon && <Icon className="h-4 w-4 text-ink-soft" />}
                    <h2 className="text-sm font-semibold text-ink">{title}</h2>
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
    icon: typeof ShoppingBag;
    tone?: 'default' | 'positive';
}) {
    const toneClass = tone === 'positive' ? 'text-emerald-700' : 'text-ink';

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

const ShowBuyer = ({ user, orderStats, totalSpent }: Props) => {
    const [generatingReset, setGeneratingReset] = useState(false);
    const [resetLink, setResetLink] = useState<ResetLinkPayload | null>(null);

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

    const isProfessional = user.role === 'professional_buyer';
    const isVerified = Boolean(user.professional_verified_at);

    // Trade-request review. `reviewing` guards against a double submit
    // approving and then declining the same request.
    const [declining, setDeclining] = useState(false);
    const [declineReason, setDeclineReason] = useState('');
    const [reviewing, setReviewing] = useState(false);

    const approveProfessional = () => {
        setReviewing(true);
        router.patch(
            route('admin.users.professional.approve', user.id),
            {},
            { onFinish: () => setReviewing(false) },
        );
    };

    const declineProfessional = () => {
        setReviewing(true);
        router.patch(
            route('admin.users.professional.decline', user.id),
            { reason: declineReason },
            {
                onFinish: () => setReviewing(false),
                onSuccess: () => {
                    setDeclining(false);
                    setDeclineReason('');
                },
            },
        );
    };

    // Nothing in the buying flow checks this yet — it just lets the admin
    // flag accounts they've reviewed, ahead of that check being wired in.
    const handleVerifyProfessional = () => {
        router.patch(route('admin.users.verify-professional', user.id));
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
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
                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                {isProfessional ? 'Acheteur pro' : 'Acheteur'}
                            </span>
                            {isProfessional && (
                                <Badge
                                    variant={isVerified ? 'default' : 'outline'}
                                    className="rounded-full"
                                >
                                    {isVerified ? (
                                        <>
                                            <CheckCircle2 className="h-3.5 w-3.5" />{' '}
                                            Vérifié
                                        </>
                                    ) : (
                                        'Non vérifié'
                                    )}
                                </Badge>
                            )}
                            {user.is_banned && (
                                <Badge
                                    variant="destructive"
                                    className="rounded-full"
                                >
                                    Bloqué
                                </Badge>
                            )}
                        </div>
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-ink-soft">
                            <Calendar className="h-3.5 w-3.5" />
                            Membre depuis le {formatDate(user.created_at)}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            className="rounded-lg"
                            disabled={generatingReset}
                            onClick={handleGenerateResetLink}
                        >
                            <KeyRound className="h-4 w-4" />
                            Copier un lien de réinitialisation
                        </Button>

                        {isProfessional && (
                            <Button
                                variant={isVerified ? 'outline' : 'default'}
                                className="rounded-lg"
                                onClick={handleVerifyProfessional}
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                {isVerified
                                    ? 'Retirer la vérification'
                                    : 'Vérifier'}
                            </Button>
                        )}

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
                    label="Dépenses totales"
                    value={formatMAD(totalSpent)}
                    icon={Wallet}
                />
                <Stat
                    label="Commandes"
                    value={orderStats.total}
                    icon={ShoppingBag}
                />
                <Stat
                    label="Livrées"
                    value={orderStats.delivered}
                    icon={CheckCircle2}
                    tone="positive"
                />
                <Stat
                    label="Annulées"
                    value={orderStats.cancelled}
                    icon={Ban}
                />
            </div>

            {/*
                Trade request queue. Rendered above the fold when pending
                because it's the one thing on this page that's waiting on the
                admin — everything else is reference material.
            */}
            {user.professional_request_status === 'pending' && (
                <div className="rounded-xl border border-amber-300/60 bg-amber-50 p-5">
                    <div className="flex items-start gap-3">
                        <Clock className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-ink">
                                Demande de compte professionnel
                            </p>
                            {user.professional_requested_at && (
                                <p className="mt-0.5 text-xs text-ink-soft">
                                    Envoyée le{' '}
                                    {formatDate(user.professional_requested_at)}
                                </p>
                            )}

                            {user.professional_request_message && (
                                <p className="mt-3 border-l-2 border-amber-300 pl-3 text-sm leading-relaxed whitespace-pre-line text-ink/85">
                                    {user.professional_request_message}
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    disabled={reviewing}
                                    onClick={approveProfessional}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    Approuver
                                </button>
                                <button
                                    type="button"
                                    disabled={reviewing}
                                    onClick={() => setDeclining(true)}
                                    className="rounded-lg border border-ink/20 px-4 py-2 text-sm font-medium text-ink transition hover:bg-ink/5 disabled:opacity-50"
                                >
                                    Refuser
                                </button>
                            </div>

                            {/* The reason is required — it's emailed to the
                                buyer and shown on their account page, so a
                                refusal always comes with something to act on. */}
                            {declining && (
                                <div className="mt-4">
                                    <label
                                        htmlFor="decline_reason"
                                        className="mb-1.5 block text-xs text-ink-soft"
                                    >
                                        Motif du refus (envoyé à l’acheteur)
                                    </label>
                                    <textarea
                                        id="decline_reason"
                                        rows={3}
                                        autoFocus
                                        maxLength={500}
                                        value={declineReason}
                                        onChange={(e) =>
                                            setDeclineReason(e.target.value)
                                        }
                                        placeholder="Ex : nous n’avons pas pu confirmer l’existence de l’établissement."
                                        className="w-full rounded-lg border border-ink/20 bg-white px-3 py-2 text-sm text-ink focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                                    />
                                    <div className="mt-2 flex gap-2">
                                        <button
                                            type="button"
                                            disabled={
                                                reviewing ||
                                                declineReason.trim() === ''
                                            }
                                            onClick={declineProfessional}
                                            className="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white transition hover:bg-ink/90 disabled:opacity-50"
                                        >
                                            Confirmer le refus
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDeclining(false)}
                                            className="rounded-lg px-4 py-2 text-sm text-ink-soft transition hover:text-ink"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* ── Body ─────────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <aside className="lg:col-span-4">
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
                        </div>
                    </Card>

                    {user.bio && (
                        <div className="mt-6">
                            <Card title="Description" icon={FileText}>
                                <p className="p-5 text-sm whitespace-pre-line text-ink-soft">
                                    {user.bio}
                                </p>
                            </Card>
                        </div>
                    )}
                </aside>

                <div className="lg:col-span-8">
                    <Card title="Historique des commandes" icon={ShoppingBag}>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>ID</TableHead>
                                    <TableHead>Boutique</TableHead>
                                    <TableHead>Montant</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">
                                        Date
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {user.orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-32 text-center text-ink-soft"
                                        >
                                            Cet utilisateur n'a pas encore passé
                                            de commande.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    user.orders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs text-ink-soft">
                                                {order.reference}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <Link
                                                    href={`/store/${order.store.slug}`}
                                                    className="font-medium text-ink transition hover:underline"
                                                >
                                                    {order.store?.store_name ||
                                                        'N/A'}
                                                </Link>
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
                </div>
            </div>

            <ResetLinkDialog
                link={resetLink}
                onClose={() => setResetLink(null)}
            />
        </div>
    );
};

ShowBuyer.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default ShowBuyer;
