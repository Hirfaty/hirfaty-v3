import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Search, Send } from 'lucide-react';
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
import { formatRelativeTime } from '@/lib/utils';
import type { PageProps, PaginatedData } from '@/types';

type PendingSignup = {
    id: number;
    email: string;
    intent: 'buyer' | 'seller';
    /** The code (or the emailed link) was confirmed; only the password step is left. */
    verified: boolean;
    /** The last code sent is past its 15 minutes — they need a new one to go on. */
    code_expired: boolean;
    locale: string;
    started_at: string | null;
    last_sent_at: string | null;
};

interface Props {
    signups: PaginatedData<PendingSignup>;
    filters: { search?: string | null };
}

const INTENT_LABEL: Record<PendingSignup['intent'], string> = {
    buyer: 'Acheteur',
    seller: 'Artisan',
};

const INTENT_BADGE: Record<PendingSignup['intent'], string> = {
    buyer: 'bg-slate-100 text-slate-700',
    seller: 'bg-emerald-100 text-emerald-700',
};

const LOCALE_LABEL: Record<string, string> = {
    fr: 'Français',
    en: 'Anglais',
    ar: 'Arabe',
};

/**
 * Where the signup stopped. Two steps sit between typing an email and having
 * an account: confirming the address (code or link), then choosing a
 * password. Nobody appears here once both are done — they are a user by then.
 */
function StepBadge({ signup }: { signup: PendingSignup }) {
    if (signup.verified) {
        return (
            <span className="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700">
                E-mail confirmé · mot de passe manquant
            </span>
        );
    }

    return (
        <span className="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">
            {signup.code_expired ? 'Code expiré' : 'Code envoyé'} · e-mail non
            confirmé
        </span>
    );
}

export default function PendingSignups({ signups, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
    const [resendingId, setResendingId] = useState<number | null>(null);
    // Skip the debounced effect's own request on first render — same as the
    // users table.
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.users.pending'),
                { search: search || undefined, page: 1 },
                {
                    preserveState: true,
                    replace: true,
                    onFinish: () => setSearching(false),
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    const handleResend = (signup: PendingSignup) => {
        if (
            !confirm(
                `Envoyer un nouveau code et un lien de confirmation à ${signup.email} ?`,
            )
        ) {
            return;
        }

        setResendingId(signup.id);
        router.post(route('admin.users.pending.resend', signup.id), undefined, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as unknown as PageProps).flash;

                if (flash?.error) {
                    toast.error(flash.error);
                } else if (flash?.success) {
                    toast.success(flash.success);
                }
            },
            onFinish: () => setResendingId(null),
        });
    };

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title="Inscriptions en cours" />
            <header className="flex flex-col gap-3">
                <Link
                    href={route('admin.users.index')}
                    className="inline-flex w-fit items-center gap-1.5 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
                    Utilisateurs
                </Link>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink">
                        Inscriptions en cours
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-ink-soft">
                        Des visiteurs qui ont saisi leur e-mail mais n’ont pas
                        encore choisi de mot de passe. Leur compte n’existe
                        qu’une fois cette dernière étape terminée — c’est
                        pourquoi ils n’apparaissent pas encore dans la liste des
                        utilisateurs.
                    </p>
                </div>
            </header>

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
                    placeholder="Adresse e-mail…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Email</TableHead>
                            <TableHead>Parcours</TableHead>
                            <TableHead>Étape</TableHead>
                            <TableHead>Langue</TableHead>
                            <TableHead>Commencée</TableHead>
                            <TableHead>Dernier e-mail</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {signups.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="h-32 text-center text-ink-soft"
                                >
                                    Aucune inscription en cours.
                                </TableCell>
                            </TableRow>
                        ) : (
                            signups.data.map((signup) => (
                                <TableRow key={signup.id}>
                                    <TableCell className="font-medium text-ink">
                                        {signup.email}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${INTENT_BADGE[signup.intent]}`}
                                        >
                                            {INTENT_LABEL[signup.intent]}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StepBadge signup={signup} />
                                    </TableCell>
                                    <TableCell className="text-sm text-ink-soft">
                                        {LOCALE_LABEL[signup.locale] ??
                                            signup.locale}
                                    </TableCell>
                                    <TableCell className="text-sm text-ink-soft">
                                        {formatRelativeTime(signup.started_at)}
                                    </TableCell>
                                    <TableCell className="text-sm text-ink-soft">
                                        {formatRelativeTime(
                                            signup.last_sent_at,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={resendingId === signup.id}
                                            onClick={() => handleResend(signup)}
                                            className="gap-1.5"
                                        >
                                            {resendingId === signup.id ? (
                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                            ) : (
                                                <Send className="h-3.5 w-3.5" />
                                            )}
                                            Renvoyer le lien
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* ── Pagination ───────────────────────────── */}
                {signups.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {signups.from}–{signups.to} sur {signups.total}
                        </p>
                        <div className="flex items-center gap-1">
                            {signups.links.map((link, i) => (
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
        </div>
    );
}

PendingSignups.layout = (page: ReactNode) => <AdminLayout children={page} />;
