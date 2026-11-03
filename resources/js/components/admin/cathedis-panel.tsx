import { router } from '@inertiajs/react';
import { FileDown, PackagePlus, RefreshCw, Truck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CathedisParcelDialog from '@/components/admin/cathedis-parcel-dialog';
import type { CathedisDraft } from '@/components/admin/cathedis-parcel-dialog';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';

/** The purchase's parcel at Cathedis, as CathedisShipmentService::panel sends it. */
export type CathedisPanelData = {
    shipment: {
        code: string;
        delivery_status: string | null;
        /** PENDING, SHIPPED, REJECTED or STANDBY. */
        delivery_status_type: string | null;
        /** Whether Cathedis has paid back the cash it collected: unpaid / paid. */
        payment_status: string | null;
        amount: number;
        payment_type: string;
        range_weight: string;
        pickup_requested_at: string | null;
        synced_at: string | null;
        closed: boolean;
    } | null;
    /** Why the parcel cannot be created at all; null when it can. */
    blocked: string | null;
    /** The parcel as it would be sent, for the desk to check; null once sent or when blocked. */
    draft: CathedisDraft | null;
    /** What to correct in the draft (an unlinked city, a phone Cathedis refuses). */
    warnings: string[];
    ranges: string[];
    payment_types: string[];
};

const TYPE_BADGE: Record<string, string> = {
    PENDING: 'bg-blue-100 text-blue-700',
    SHIPPED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-rose-100 text-rose-700',
    STANDBY: 'bg-amber-100 text-amber-700',
};

const AWAITING_PICKUP = 'En Attente Ramassage';

const mad = (value: number) =>
    new Intl.NumberFormat('fr-MA', {
        style: 'currency',
        currency: 'MAD',
    }).format(value);

/** Toast the flash a redirect back carried — success or Cathedis's refusal. */
function toastFlash(props: unknown, fallback: string) {
    const flash = (props as { flash?: { success?: string; error?: string } })
        .flash;

    if (flash?.error) {
        toast.error(flash.error);
    } else {
        toast.success(flash?.success ?? fallback);
    }
}

/**
 * The desk's Cathedis panel on a purchase: create the parcel (one for the
 * whole basket), print its slip, read its status now, ask for a pickup. See
 * Admin\CathedisShipmentController and CathedisShipmentService.
 */
export default function CathedisPanel({
    reference,
    data,
}: {
    /** The order the requests are made against — any order of the purchase. */
    reference: string;
    data: CathedisPanelData;
}) {
    const [creating, setCreating] = useState(false);
    const [busy, setBusy] = useState(false);

    const shipment = data.shipment;

    const post = (url: string, done: string) => {
        setBusy(true);
        router.post(url, undefined, {
            preserveScroll: true,
            onSuccess: (page) => toastFlash(page.props, done),
            onError: (errors) =>
                toast.error(
                    (Object.values(errors)[0] as string) ??
                        'Action impossible.',
                ),
            onFinish: () => setBusy(false),
        });
    };

    /*
     * Fetched rather than linked: Cathedis renders the slip on demand, and a
     * refusal comes back as JSON to show instead of a blank download.
     */
    const download = async (thermal: boolean) => {
        setBusy(true);

        try {
            const response = await fetch(
                route('admin.orders.cathedis.label', {
                    order: reference,
                    ...(thermal ? { format: '10x10' } : {}),
                }),
                { headers: { Accept: 'application/pdf, application/json' } },
            );

            if (
                !response.ok ||
                !response.headers.get('Content-Type')?.includes('pdf')
            ) {
                const body = await response.json().catch(() => null);
                toast.error(body?.message ?? 'Bon de livraison indisponible.');

                return;
            }

            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `bon-livraison-${shipment?.code ?? reference}${thermal ? '-10x10' : ''}.pdf`;
            link.click();
            URL.revokeObjectURL(url);
        } catch {
            toast.error('Bon de livraison indisponible.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-ink">
                <Truck className="h-4 w-4 text-ink-soft/60" />
                Cathedis
            </h2>

            {shipment ? (
                <>
                    <dl className="mt-3 space-y-2 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-ink-soft">Colis</dt>
                            <dd className="font-medium text-ink tabular-nums">
                                {shipment.code}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-ink-soft">Statut</dt>
                            <dd>
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                        TYPE_BADGE[
                                            shipment.delivery_status_type ?? ''
                                        ] ?? 'bg-slate-100 text-slate-700'
                                    }`}
                                >
                                    {shipment.delivery_status ?? '—'}
                                </span>
                            </dd>
                        </div>
                        <div className="flex justify-between gap-2">
                            <dt className="text-ink-soft">
                                {shipment.payment_type === 'PAYE'
                                    ? 'Payé en ligne'
                                    : 'À encaisser par Cathedis'}
                            </dt>
                            <dd className="text-ink tabular-nums">
                                {mad(shipment.amount)}
                            </dd>
                        </div>
                        {shipment.payment_type !== 'PAYE' && (
                            <div className="flex justify-between gap-2">
                                <dt className="text-ink-soft">
                                    Fonds reversés
                                </dt>
                                <dd className="text-ink">
                                    {shipment.payment_status === 'paid'
                                        ? 'Oui'
                                        : 'Pas encore'}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-2">
                            <dt className="text-ink-soft">Poids</dt>
                            <dd className="text-right text-ink">
                                {shipment.range_weight}
                            </dd>
                        </div>
                    </dl>

                    <p className="mt-3 text-xs text-ink-soft">
                        {shipment.pickup_requested_at &&
                            `Ramassage demandé le ${formatDate(shipment.pickup_requested_at)}. `}
                        {shipment.synced_at &&
                            `Lu chez Cathedis le ${formatDate(shipment.synced_at)}.`}
                    </p>

                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy}
                            onClick={() => download(false)}
                        >
                            <FileDown className="h-4 w-4" />
                            Bon A5
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy}
                            onClick={() => download(true)}
                        >
                            <FileDown className="h-4 w-4" />
                            Étiquette 10×10
                        </Button>
                    </div>

                    <div className="mt-2 grid gap-2">
                        {!shipment.closed &&
                            (shipment.delivery_status ?? AWAITING_PICKUP) ===
                                AWAITING_PICKUP && (
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() =>
                                        post(
                                            route('admin.cathedis.pickup'),
                                            'Ramassage demandé.',
                                        )
                                    }
                                >
                                    <Truck className="h-4 w-4" />
                                    Demander le ramassage
                                </Button>
                            )}
                        {!shipment.closed && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                    post(
                                        route(
                                            'admin.orders.cathedis.refresh',
                                            reference,
                                        ),
                                        'Statut lu chez Cathedis.',
                                    )
                                }
                            >
                                <RefreshCw className="h-4 w-4" />
                                Actualiser le statut
                            </Button>
                        )}
                    </div>

                    {!shipment.closed &&
                        shipment.delivery_status === AWAITING_PICKUP && (
                            <p className="mt-2 text-xs leading-relaxed text-ink-soft">
                                Le ramassage concerne tous les colis en attente,
                                pas seulement celui-ci.
                            </p>
                        )}
                </>
            ) : (
                <>
                    <p className="mt-2 text-xs leading-relaxed text-ink-soft">
                        Un seul colis pour tout l’achat. L’achat passe en «
                        Expédiée » quand Cathedis l’a ramassé, puis en « Livrée
                        » : l’acheteur reçoit les e-mails habituels.
                    </p>
                    {(data.blocked || data.warnings.length > 0) && (
                        <div className="mt-3 space-y-1 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
                            {data.blocked ? (
                                <p>{data.blocked}</p>
                            ) : (
                                data.warnings.map((warning) => (
                                    <p key={warning}>{warning}</p>
                                ))
                            )}
                        </div>
                    )}
                    <Button
                        type="button"
                        className="mt-3 w-full"
                        disabled={data.draft === null || busy}
                        onClick={() => setCreating(true)}
                    >
                        <PackagePlus className="h-4 w-4" />
                        Expédier avec Cathedis
                    </Button>
                </>
            )}

            {data.draft && (
                <CathedisParcelDialog
                    open={creating}
                    onOpenChange={setCreating}
                    reference={reference}
                    draft={data.draft}
                    warnings={data.warnings}
                    ranges={data.ranges}
                    paymentTypes={data.payment_types}
                />
            )}
        </div>
    );
}
