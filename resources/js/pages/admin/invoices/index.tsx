import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Download, Loader2, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin/layout';
import { formatDate } from '@/lib/utils';

type PaymentMethod = 'card' | 'paypal' | 'cod';

type InvoiceState =
    | 'paid'
    | 'collected'
    | 'to_collect'
    | 'refunded'
    | 'partially_refunded'
    | 'cancelled';

/** One invoice, as AdminInvoiceListService::row shapes it. */
type Invoice = {
    /** The purchase reference — the number printed on the PDF. */
    number: string;
    primary_reference: string;
    issued_at: string;
    buyer: { name: string; email: string | null };
    stores: string[];
    /** MAD, what the buyer paid: products plus delivery. */
    products_total: number;
    delivery_total: number;
    total: number;
    payment_method: PaymentMethod;
    state: InvoiceState;
    /** What Payzone or PayPal took, in its own currency. Null for cash. */
    payment: {
        provider: 'payzone' | 'paypal';
        amount: number;
        currency: string;
        transaction_id: string | null;
    } | null;
};

type Pagination = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const METHOD_LABEL: Record<PaymentMethod, string> = {
    card: 'Carte (Payzone)',
    paypal: 'PayPal',
    cod: 'À la livraison',
};

const STATE_BADGE: Record<InvoiceState, string> = {
    paid: 'bg-emerald-100 text-emerald-700',
    collected: 'bg-emerald-100 text-emerald-700',
    to_collect: 'bg-amber-100 text-amber-700',
    refunded: 'bg-blue-100 text-blue-700',
    partially_refunded: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const STATE_LABEL: Record<InvoiceState, string> = {
    paid: 'Payée',
    collected: 'Encaissée',
    to_collect: 'À encaisser',
    refunded: 'Remboursée',
    partially_refunded: 'Remboursée en partie',
    cancelled: 'Annulée',
};

const mad = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

const charged = (amount: number, currency: string) =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(
        amount,
    );

/**
 * Every invoice Hirfaty has issued — one per purchase, the PDF the buyer
 * downloads from their order history. See Admin\InvoiceController.
 */
const Index = ({
    invoices,
    pagination,
    counts,
    filters,
}: {
    invoices: Invoice[];
    pagination: Pagination;
    counts: Record<'all' | PaymentMethod, number>;
    filters: { search?: string; method?: string };
}) => {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [searching, setSearching] = useState(false);
    // Skip the debounced effect's own request on first render — otherwise
    // landing with ?search= already set fires a redundant fetch.
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        setSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('admin.invoices.index'),
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

    const setMethod = (method: PaymentMethod | null) => {
        router.get(
            route('admin.invoices.index'),
            { ...filters, method: method ?? undefined, page: 1 },
            { preserveState: true },
        );
    };

    const tabs = [
        { value: null, label: 'Toutes', count: counts.all },
        { value: 'card', label: 'Carte', count: counts.card },
        { value: 'paypal', label: 'PayPal', count: counts.paypal },
        { value: 'cod', label: 'À la livraison', count: counts.cod },
    ] as const;

    return (
        <div className="flex flex-col gap-6 p-6 md:p-8">
            <Head title={t('titles.admin.invoices')} />
            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-ink">
                    Factures
                </h1>
                <p className="mt-1 text-sm text-ink-soft">
                    {pagination.total} facture{pagination.total > 1 ? 's' : ''}{' '}
                    · une par achat, montants payés par l’acheteur (produits +
                    livraison)
                </p>
            </header>

            {/* ── Payment-method tabs ──────────────────────── */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = (filters.method ?? null) === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => setMethod(tab.value)}
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
                    placeholder="N° de facture, nom ou e-mail de l’acheteur…"
                    className="w-full rounded-lg border border-border bg-card py-2.5 pr-3 pl-9 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                />
            </div>

            {/* ── Table ────────────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-xs font-medium text-ink-soft">
                            <tr>
                                <th className="px-5 py-3">Facture</th>
                                <th className="px-5 py-3">Client</th>
                                <th className="px-5 py-3">Ateliers</th>
                                <th className="px-5 py-3">Montant</th>
                                <th className="px-5 py-3">Paiement</th>
                                <th className="px-5 py-3">État</th>
                                <th className="px-5 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {invoices.map((invoice) => (
                                <tr
                                    key={invoice.number}
                                    className="transition hover:bg-muted/30"
                                >
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-ink">
                                            {invoice.number}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {formatDate(invoice.issued_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="font-medium text-ink">
                                            {invoice.buyer.name}
                                        </p>
                                        {invoice.buyer.email && (
                                            <p className="text-xs text-ink-soft">
                                                {invoice.buyer.email}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 text-ink">
                                        {invoice.stores.join(', ') || '—'}
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="font-medium whitespace-nowrap text-ink">
                                            {mad(invoice.total)}
                                        </p>
                                        <p className="text-xs whitespace-nowrap text-ink-soft">
                                            Livraison :{' '}
                                            {mad(invoice.delivery_total)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">
                                        <p className="text-ink">
                                            {
                                                METHOD_LABEL[
                                                    invoice.payment_method
                                                ]
                                            }
                                        </p>
                                        {invoice.payment &&
                                            invoice.payment.currency !==
                                                'MAD' && (
                                                <p className="text-xs whitespace-nowrap text-ink-soft">
                                                    Débité :{' '}
                                                    {charged(
                                                        invoice.payment.amount,
                                                        invoice.payment
                                                            .currency,
                                                    )}
                                                </p>
                                            )}
                                        {invoice.payment?.transaction_id && (
                                            <p
                                                className="font-mono text-[11px] text-ink-soft"
                                                title={
                                                    invoice.payment.provider ===
                                                    'paypal'
                                                        ? 'Identifiant de la transaction PayPal'
                                                        : 'Identifiant de la transaction Payzone'
                                                }
                                            >
                                                {invoice.payment.transaction_id}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap ${STATE_BADGE[invoice.state]}`}
                                        >
                                            {STATE_LABEL[invoice.state]}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {/* A file, not a page: a plain
                                                link, so Inertia leaves it
                                                to the browser. */}
                                            <a
                                                href={route(
                                                    'admin.invoices.download',
                                                    invoice.number,
                                                )}
                                                aria-label={`Télécharger la facture ${invoice.number}`}
                                                title="Télécharger le PDF"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-ink-soft transition hover:bg-muted hover:text-ink"
                                            >
                                                <Download className="h-4 w-4" />
                                            </a>
                                            <Link
                                                href={route(
                                                    'admin.orders.show',
                                                    invoice.primary_reference,
                                                )}
                                                aria-label={`Voir l’achat ${invoice.number}`}
                                                title="Voir l’achat"
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-ink-soft transition hover:bg-muted hover:text-ink"
                                            >
                                                <ArrowUpRight className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {invoices.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-5 py-10 text-center text-ink-soft"
                                    >
                                        Aucune facture trouvée.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-5 py-3.5">
                        <p className="text-xs text-ink-soft">
                            {pagination.from}–{pagination.to} sur{' '}
                            {pagination.total}
                        </p>
                        <div className="flex items-center gap-1">
                            {pagination.links.map((link, i) => (
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
};

Index.layout = (page: ReactNode) => <AdminLayout children={page} />;
export default Index;
