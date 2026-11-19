import { router } from '@inertiajs/react';
import {
    ExternalLink,
    FileDown,
    PackagePlus,
    Plane,
    RefreshCw,
    Truck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DhlShipmentDialog from '@/components/admin/dhl-shipment-dialog';
import type { DhlDraft } from '@/components/admin/dhl-shipment-dialog';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';

/** The purchase's DHL Express shipment, as DhlShipmentService::panel sends it. */
export type DhlPanelData = {
    environment: 'test' | 'production';
    shipment: {
        tracking_number: string;
        tracking_url: string;
        pieces: string[];
        incoterm: string;
        planned_shipping_date: string | null;
        estimated_delivery_date: string | null;
        /** What DHL priced it at for Hirfaty's account. */
        charge: number | null;
        charge_currency: string | null;
        pickup: {
            number: string | null;
            requested_at: string | null;
            cancelled_at: string | null;
            active: boolean;
            bookable: boolean;
        };
        /** DHL's last checkpoint: PU, AF, WC, OK… */
        status_code: string | null;
        status_description: string | null;
        /** The latest checkpoints, newest first. */
        events: {
            code: string;
            description: string;
            at: string | null;
            location: string | null;
        }[];
        /** label, invoice, waybillDoc, pod. */
        documents: string[];
        issue: boolean;
        delivered: boolean;
        synced_at: string | null;
        closed: boolean;
    } | null;
    blocked: string | null;
    draft: DhlDraft | null;
    warnings: string[];
    incoterms: string[];
    countries: { code: string; name: string }[];
    max_days_ahead: number;
    /** The desk's closing time and where the courier asks, from config. */
    pickup_defaults: { close_time: string; location: string };
};

const DOCUMENT_LABEL: Record<string, string> = {
    label: 'Étiquette',
    invoice: 'Facture commerciale',
    waybillDoc: 'Lettre de transport',
    pod: 'Preuve de livraison',
};

/** Toast the flash a redirect back carried — success or DHL's refusal. */
function toastFlash(props: unknown, fallback: string) {
    const flash = (props as { flash?: { success?: string; error?: string } })
        .flash;

    if (flash?.error) {
        toast.error(flash.error, { duration: 15000 });
    } else {
        toast.success(flash?.success ?? fallback);
    }
}

function tomorrow(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * The desk's DHL Express panel on a purchase abroad: create the shipment (one
 * for the whole basket), download its label and customs invoice, follow its
 * checkpoints, book or cancel the courier. See Admin\DhlShipmentController
 * and DhlShipmentService.
 */
export default function DhlPanel({
    reference,
    data,
}: {
    /** The order the requests are made against — any order of the purchase. */
    reference: string;
    data: DhlPanelData;
}) {
    const [creating, setCreating] = useState(false);
    const [busy, setBusy] = useState(false);
    const [pickupDate, setPickupDate] = useState(tomorrow);

    const shipment = data.shipment;

    const send = (method: 'post' | 'delete', url: string, done: string) => {
        setBusy(true);
        router.visit(url, {
            method,
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

    const bookPickup = () => {
        setBusy(true);
        router.post(
            route('admin.orders.dhl.pickup', reference),
            {
                date: pickupDate,
                close_time: data.pickup_defaults.close_time,
                location: data.pickup_defaults.location,
            },
            {
                preserveScroll: true,
                onSuccess: (page) =>
                    toastFlash(page.props, 'Ramassage réservé.'),
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Ramassage non réservé.',
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    /*
     * Fetched rather than linked, so a refusal (no proof of delivery yet)
     * comes back as a message instead of a blank download.
     */
    const download = async (type: string) => {
        setBusy(true);

        try {
            const response = await fetch(
                route('admin.orders.dhl.document', { order: reference, type }),
                { headers: { Accept: 'application/pdf, application/json' } },
            );

            if (
                !response.ok ||
                !response.headers.get('Content-Type')?.includes('pdf')
            ) {
                const body = await response.json().catch(() => null);
                toast.error(body?.message ?? 'Document indisponible.');

                return;
            }

            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `dhl-${type}-${shipment?.tracking_number ?? reference}.pdf`;
            link.click();
            URL.revokeObjectURL(url);
        } catch {
            toast.error('Document indisponible.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-ink">
                <Plane className="h-4 w-4 text-ink-soft/60" />
                DHL Express
                {data.environment === 'test' && (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                        TEST
                    </span>
                )}
            </h2>

            {shipment ? (
                <>
                    <dl className="mt-3 space-y-2 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-ink-soft">N° de suivi</dt>
                            <dd>
                                <a
                                    href={shipment.tracking_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 font-medium text-ink tabular-nums hover:underline"
                                >
                                    {shipment.tracking_number}
                                    <ExternalLink className="h-3 w-3" />
                                </a>
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-ink-soft">Statut</dt>
                            <dd className="text-right">
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                        shipment.delivered
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : shipment.issue
                                              ? 'bg-rose-100 text-rose-700'
                                              : shipment.status_code
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'bg-slate-100 text-slate-700'
                                    }`}
                                >
                                    {shipment.status_description ??
                                        'Pas encore pris en charge'}
                                </span>
                            </dd>
                        </div>
                        {shipment.estimated_delivery_date && (
                            <div className="flex justify-between gap-2">
                                <dt className="text-ink-soft">
                                    Livraison prévue
                                </dt>
                                <dd className="text-ink">
                                    {formatDate(
                                        shipment.estimated_delivery_date,
                                    )}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-2">
                            <dt className="text-ink-soft">Droits et taxes</dt>
                            <dd className="text-ink">
                                {shipment.incoterm === 'DDP'
                                    ? 'Payés par Hirfaty'
                                    : 'À la charge de l’acheteur'}
                            </dd>
                        </div>
                        {shipment.charge !== null && (
                            <div className="flex justify-between gap-2">
                                <dt className="text-ink-soft">Tarif DHL</dt>
                                <dd className="text-ink tabular-nums">
                                    {shipment.charge.toLocaleString('fr-MA', {
                                        minimumFractionDigits: 2,
                                    })}{' '}
                                    {shipment.charge_currency}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-2">
                            <dt className="text-ink-soft">Ramassage</dt>
                            <dd className="text-right text-ink">
                                {shipment.pickup.active
                                    ? `Réservé (${shipment.pickup.number})`
                                    : shipment.pickup.cancelled_at
                                      ? 'Annulé'
                                      : 'Non réservé'}
                            </dd>
                        </div>
                    </dl>

                    {shipment.events.length > 0 && (
                        <ol className="mt-3 space-y-1.5 border-s border-border ps-3 text-xs">
                            {shipment.events.map((event) => (
                                <li key={`${event.code}-${event.at}`}>
                                    <span className="font-medium text-ink">
                                        {event.description}
                                    </span>
                                    <span className="block text-ink-soft">
                                        {[
                                            event.at && formatDate(event.at),
                                            event.location,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}

                    {shipment.synced_at && (
                        <p className="mt-3 text-xs text-ink-soft">
                            Lu chez DHL le {formatDate(shipment.synced_at)}.
                        </p>
                    )}

                    <div className="mt-3 grid grid-cols-2 gap-2">
                        {shipment.documents.map((type) => (
                            <Button
                                key={type}
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() => download(type)}
                            >
                                <FileDown className="h-4 w-4" />
                                {DOCUMENT_LABEL[type] ?? type}
                            </Button>
                        ))}
                    </div>

                    <div className="mt-2 grid gap-2">
                        {shipment.pickup.bookable && (
                            <div className="flex gap-2">
                                <input
                                    type="date"
                                    aria-label="Date du ramassage"
                                    value={pickupDate}
                                    onChange={(e) =>
                                        setPickupDate(e.target.value)
                                    }
                                    className="min-w-0 flex-1 rounded-lg border border-border bg-card px-2 py-1.5 text-sm text-ink focus:border-ink focus:outline-none"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={busy || pickupDate === ''}
                                    onClick={bookPickup}
                                >
                                    <Truck className="h-4 w-4" />
                                    Réserver le coursier
                                </Button>
                            </div>
                        )}
                        {shipment.pickup.active && !shipment.status_code && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Annuler le ramassage DHL ?',
                                        )
                                    ) {
                                        send(
                                            'delete',
                                            route(
                                                'admin.orders.dhl.pickup.cancel',
                                                reference,
                                            ),
                                            'Ramassage annulé.',
                                        );
                                    }
                                }}
                            >
                                <X className="h-4 w-4" />
                                Annuler le ramassage
                            </Button>
                        )}
                        {!shipment.closed && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                    send(
                                        'post',
                                        route(
                                            'admin.orders.dhl.refresh',
                                            reference,
                                        ),
                                        'Suivi lu chez DHL.',
                                    )
                                }
                            >
                                <RefreshCw className="h-4 w-4" />
                                Actualiser le suivi
                            </Button>
                        )}
                    </div>
                </>
            ) : (
                <>
                    <p className="mt-2 text-xs leading-relaxed text-ink-soft">
                        Une seule expédition pour tout l’achat. L’achat passe en
                        « Expédiée » au premier passage DHL, puis en « Livrée »
                        : l’acheteur reçoit les e-mails habituels.
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
                        Expédier avec DHL Express
                    </Button>
                </>
            )}

            {data.draft && (
                <DhlShipmentDialog
                    open={creating}
                    onOpenChange={setCreating}
                    reference={reference}
                    draft={data.draft}
                    warnings={data.warnings}
                    incoterms={data.incoterms}
                    countries={data.countries}
                    maxDaysAhead={data.max_days_ahead}
                    environment={data.environment}
                />
            )}
        </div>
    );
}
