import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** The shipment as Hirfaty would send it — DhlShipmentService::draft. */
export type DhlDraft = {
    receiver: {
        name: string;
        phone: string;
        email: string;
        address_line1: string;
        address_line2: string;
        address_line3: string;
        city: string;
        postal_code: string;
        country: string;
    };
    /** kg and cm. */
    packages: {
        weight: number;
        length: number;
        width: number;
        height: number;
    }[];
    /** Price in MAD and weight in kg, per unit. */
    items: {
        description: string;
        quantity: number;
        price: number;
        hs_code: string | null;
        origin_country: string;
        weight: number;
    }[];
    incoterm: string;
    /** Y-m-d, Casablanca time. */
    shipping_date: string;
    pickup: { requested: boolean; close_time: string; location: string };
    notify_buyer: boolean;
};

type CheckResult = { ok: boolean; message: string; details: string[] };

const INCOTERM_LABEL: Record<string, string> = {
    DAP: 'DAP — l’acheteur paie les droits et taxes à la livraison',
    DDP: 'DDP — Hirfaty paie les droits et taxes (facturés par DHL)',
};

const inputClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-ink transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none';

/** Laravel accepts the session CSRF token as an X-XSRF-TOKEN header. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function Field({
    label,
    hint,
    children,
    className,
}: {
    label: string;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label className={`block ${className ?? ''}`}>
            <span className="mb-1.5 block text-xs font-medium text-ink">
                {label}
            </span>
            {children}
            {hint && (
                <span className="mt-1 block text-xs text-ink-soft">{hint}</span>
            )}
        </label>
    );
}

/** A day from today as Y-m-d, in the desk's own time zone. */
function addDays(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

/**
 * The shipment the desk is about to hand DHL Express, every field filled from
 * the orders and editable — the town of an address typed as one line, the
 * customs description and HS code of each line, the boxes as packed. "Vérifier
 * avec DHL" has DHL check it all without creating anything. The server checks
 * it again (Admin\DhlShipmentController, DhlShipmentService::create).
 */
export default function DhlShipmentDialog({
    open,
    onOpenChange,
    reference,
    draft,
    warnings,
    incoterms,
    countries,
    maxDaysAhead,
    environment,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The order the request is made against — any order of the purchase. */
    reference: string;
    draft: DhlDraft;
    warnings: string[];
    incoterms: string[];
    countries: { code: string; name: string }[];
    maxDaysAhead: number;
    environment: 'test' | 'production';
}) {
    const [form, setForm] = useState<DhlDraft>(draft);
    const [towns, setTowns] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [check, setCheck] = useState<CheckResult | null>(null);

    const receiver = form.receiver;

    const setReceiver = (key: keyof DhlDraft['receiver'], value: string) => {
        setCheck(null);
        setForm((current) => ({
            ...current,
            receiver: { ...current.receiver, [key]: value },
        }));
    };

    const setPackage = (
        index: number,
        key: keyof DhlDraft['packages'][number],
        value: number,
    ) => {
        setCheck(null);
        setForm((current) => ({
            ...current,
            packages: current.packages.map((p, i) =>
                i === index ? { ...p, [key]: value } : p,
            ),
        }));
    };

    const setItem = <K extends keyof DhlDraft['items'][number]>(
        index: number,
        key: K,
        value: DhlDraft['items'][number][K],
    ) => {
        setCheck(null);
        setForm((current) => ({
            ...current,
            items: current.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        }));
    };

    /*
     * The buyer typed their address as one line, so the town is not a field
     * of its own: ask DHL which towns it files the postcode under and offer
     * them, filling the field when it is still empty.
     */
    useEffect(() => {
        const country = receiver.country;
        const postal = receiver.postal_code.trim();

        if (!open || country.length !== 2 || postal === '') {
            return;
        }

        const timer = window.setTimeout(() => {
            fetch(route('admin.dhl.towns', { country, postal_code: postal }), {
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { towns?: string[] } | null) => {
                    const found = body?.towns ?? [];
                    setTowns(found);

                    if (found.length > 0) {
                        setForm((current) =>
                            current.receiver.city.trim() === ''
                                ? {
                                      ...current,
                                      receiver: {
                                          ...current.receiver,
                                          city: found[0],
                                      },
                                  }
                                : current,
                        );
                    }
                })
                .catch(() => setTowns([]));
        }, 400);

        return () => window.clearTimeout(timer);
    }, [open, receiver.country, receiver.postal_code]);

    const declared = form.items.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0,
    );
    const incomplete =
        receiver.name.trim() === '' ||
        receiver.phone.trim() === '' ||
        receiver.address_line1.trim() === '' ||
        receiver.city.trim() === '' ||
        receiver.country === '' ||
        form.items.some((item) => item.description.trim() === '') ||
        form.packages.length === 0;

    const payload = () => ({
        ...form,
        receiver: {
            ...receiver,
            email: receiver.email.trim() || null,
            address_line2: receiver.address_line2.trim() || null,
            address_line3: receiver.address_line3.trim() || null,
            postal_code: receiver.postal_code.trim() || null,
        },
        items: form.items.map((item) => ({
            ...item,
            hs_code: item.hs_code?.trim() || null,
        })),
    });

    const runCheck = async () => {
        setBusy(true);
        setCheck(null);

        try {
            const response = await fetch(
                route('admin.orders.dhl.check', reference),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload()),
                },
            );
            const body = await response.json().catch(() => null);

            if (response.status === 422 && body?.errors) {
                // Laravel's own validation: the form, before DHL.
                setCheck({
                    ok: false,
                    message: 'Formulaire incomplet.',
                    details: Object.values(
                        body.errors as Record<string, string[]>,
                    ).flat(),
                });

                return;
            }

            setCheck({
                ok: Boolean(body?.ok),
                message: body?.message ?? 'Vérification impossible.',
                details: body?.details ?? [],
            });
        } catch {
            setCheck({
                ok: false,
                message: 'DHL n’a pas pu être joint.',
                details: [],
            });
        } finally {
            setBusy(false);
        }
    };

    const submit = () => {
        setBusy(true);

        router.post(route('admin.orders.dhl.store', reference), payload(), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (
                    page.props as {
                        flash?: { success?: string; error?: string };
                    }
                ).flash;

                if (flash?.error) {
                    toast.error(flash.error, { duration: 15000 });

                    return;
                }

                toast.success(flash?.success ?? 'Expédition DHL créée.');
                onOpenChange(false);
            },
            onError: (errors) =>
                toast.error(
                    (Object.values(errors)[0] as string) ??
                        'Expédition non créée.',
                ),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Créer l’expédition DHL Express
                        {environment === 'test' && (
                            <span className="ms-2 rounded-full bg-amber-100 px-2 py-0.5 align-middle text-xs font-medium text-amber-800">
                                TEST
                            </span>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        Vérifiez ce qui sera envoyé à DHL. L’expédition crée le
                        numéro de suivi de tout l’achat, l’étiquette et la
                        facture commerciale pour la douane.
                    </DialogDescription>
                </DialogHeader>

                {warnings.length > 0 && (
                    <div className="space-y-1.5 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
                        {warnings.map((warning) => (
                            <p key={warning} className="flex gap-1.5">
                                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {warning}
                            </p>
                        ))}
                    </div>
                )}

                {/* ── Receiver ─────────────────────────── */}
                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Destinataire
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field
                            label="Nom complet"
                            hint="Prénom et nom, sans « M. » ni « Mme »."
                        >
                            <input
                                value={receiver.name}
                                onChange={(e) =>
                                    setReceiver('name', e.target.value)
                                }
                                maxLength={255}
                                className={inputClass}
                            />
                        </Field>
                        <Field label="Téléphone" hint="Avec l’indicatif : +33…">
                            <input
                                value={receiver.phone}
                                onChange={(e) =>
                                    setReceiver('phone', e.target.value)
                                }
                                maxLength={70}
                                inputMode="tel"
                                className={inputClass}
                            />
                        </Field>
                        <Field label="E-mail">
                            <input
                                type="email"
                                value={receiver.email}
                                onChange={(e) =>
                                    setReceiver('email', e.target.value)
                                }
                                maxLength={70}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    {(
                        [
                            'address_line1',
                            'address_line2',
                            'address_line3',
                        ] as const
                    ).map((key, index) => (
                        <Field
                            key={key}
                            label={`Adresse, ligne ${index + 1}${index > 0 ? ' (facultatif)' : ''}`}
                            hint={
                                index === 0 &&
                                '45 caractères par ligne au plus (DHL).'
                            }
                        >
                            <input
                                value={receiver[key]}
                                onChange={(e) =>
                                    setReceiver(key, e.target.value)
                                }
                                maxLength={45}
                                className={inputClass}
                            />
                        </Field>
                    ))}
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label="Pays">
                            <select
                                value={receiver.country}
                                onChange={(e) =>
                                    setReceiver('country', e.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="" disabled>
                                    Choisir…
                                </option>
                                {countries.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Code postal">
                            <input
                                value={receiver.postal_code}
                                onChange={(e) =>
                                    setReceiver('postal_code', e.target.value)
                                }
                                maxLength={12}
                                className={inputClass}
                            />
                        </Field>
                        <Field
                            label="Ville"
                            hint={
                                towns.length > 0 &&
                                `DHL connaît ce code postal à : ${towns.join(', ')}`
                            }
                        >
                            <input
                                value={receiver.city}
                                onChange={(e) =>
                                    setReceiver('city', e.target.value)
                                }
                                list="dhl-towns"
                                maxLength={45}
                                className={inputClass}
                            />
                            <datalist id="dhl-towns">
                                {towns.map((town) => (
                                    <option key={town} value={town} />
                                ))}
                            </datalist>
                        </Field>
                    </div>
                </section>

                {/* ── Boxes ────────────────────────────── */}
                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            Colis (poids réel et dimensions du carton)
                        </h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={form.packages.length >= 20}
                            onClick={() =>
                                setForm((current) => ({
                                    ...current,
                                    packages: [
                                        ...current.packages,
                                        {
                                            weight: 1,
                                            length: 30,
                                            width: 20,
                                            height: 15,
                                        },
                                    ],
                                }))
                            }
                        >
                            <Plus className="h-4 w-4" />
                            Colis
                        </Button>
                    </div>
                    {form.packages.map((box, index) => (
                        <div
                            key={index}
                            className="grid grid-cols-2 items-end gap-2 sm:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
                        >
                            {(
                                [
                                    ['weight', 'Poids (kg)', 0.1],
                                    ['length', 'Long. (cm)', 1],
                                    ['width', 'Larg. (cm)', 1],
                                    ['height', 'Haut. (cm)', 1],
                                ] as const
                            ).map(([key, label, min]) => (
                                <Field key={key} label={label}>
                                    <input
                                        type="number"
                                        min={min}
                                        step={key === 'weight' ? '0.1' : '1'}
                                        value={box[key]}
                                        onChange={(e) =>
                                            setPackage(
                                                index,
                                                key,
                                                Number(e.target.value) || 0,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </Field>
                            ))}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Retirer ce colis"
                                disabled={form.packages.length <= 1}
                                onClick={() =>
                                    setForm((current) => ({
                                        ...current,
                                        packages: current.packages.filter(
                                            (_, i) => i !== index,
                                        ),
                                    }))
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </section>

                {/* ── Customs lines ────────────────────── */}
                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Déclaration en douane — valeur{' '}
                        {declared.toLocaleString('fr-MA', {
                            minimumFractionDigits: 2,
                        })}{' '}
                        MAD
                    </h3>
                    <p className="text-xs text-ink-soft">
                        En anglais de préférence, précis (« Handmade ceramic
                        plate », pas « Gift ») : DHL refuse les descriptions
                        vagues. Lettres sans accent seulement.
                    </p>
                    {form.items.map((item, index) => (
                        <div
                            key={index}
                            className="space-y-2 rounded-lg border border-border p-3"
                        >
                            <Field
                                label={`Article ${index + 1} — ${item.quantity} × ${item.price.toLocaleString('fr-MA')} MAD`}
                            >
                                <input
                                    value={item.description}
                                    onChange={(e) =>
                                        setItem(
                                            index,
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={512}
                                    className={inputClass}
                                />
                            </Field>
                            <div className="grid grid-cols-3 gap-2">
                                <Field label="Code SH">
                                    <input
                                        value={item.hs_code ?? ''}
                                        onChange={(e) =>
                                            setItem(
                                                index,
                                                'hs_code',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={18}
                                        placeholder="ex. 691200"
                                        className={inputClass}
                                    />
                                </Field>
                                <Field label="Poids unitaire (kg)">
                                    <input
                                        type="number"
                                        min={0.001}
                                        step="0.01"
                                        value={item.weight}
                                        onChange={(e) =>
                                            setItem(
                                                index,
                                                'weight',
                                                Number(e.target.value) || 0,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </Field>
                                <Field label="Pays d’origine">
                                    <input
                                        value={item.origin_country}
                                        onChange={(e) =>
                                            setItem(
                                                index,
                                                'origin_country',
                                                e.target.value
                                                    .toUpperCase()
                                                    .slice(0, 2),
                                            )
                                        }
                                        maxLength={2}
                                        className={inputClass}
                                    />
                                </Field>
                            </div>
                        </div>
                    ))}
                </section>

                {/* ── Shipping ─────────────────────────── */}
                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Expédition
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field
                            label="Date d’expédition"
                            hint="Aujourd’hui : dans l’heure. Un autre jour : à 10 h."
                        >
                            <input
                                type="date"
                                min={addDays(0)}
                                max={addDays(maxDaysAhead)}
                                value={form.shipping_date}
                                onChange={(e) => {
                                    setCheck(null);
                                    setForm((current) => ({
                                        ...current,
                                        shipping_date: e.target.value,
                                    }));
                                }}
                                className={inputClass}
                            />
                        </Field>
                        <Field label="Droits et taxes">
                            <select
                                value={form.incoterm}
                                onChange={(e) => {
                                    setCheck(null);
                                    setForm((current) => ({
                                        ...current,
                                        incoterm: e.target.value,
                                    }));
                                }}
                                className={inputClass}
                            >
                                {incoterms.map((code) => (
                                    <option key={code} value={code}>
                                        {INCOTERM_LABEL[code] ?? code}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/30 p-3">
                        <input
                            type="checkbox"
                            checked={form.pickup.requested}
                            onChange={(e) =>
                                setForm((current) => ({
                                    ...current,
                                    pickup: {
                                        ...current.pickup,
                                        requested: e.target.checked,
                                    },
                                }))
                            }
                            className="mt-0.5 h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                        />
                        <span className="text-sm">
                            <span className="font-medium text-ink">
                                Réserver le coursier DHL
                            </span>
                            <span className="mt-0.5 block text-xs text-ink-soft">
                                Sans réservation, déposez le colis dans un point
                                DHL.
                            </span>
                        </span>
                    </label>
                    {form.pickup.requested && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Heure de fermeture">
                                <input
                                    type="time"
                                    value={form.pickup.close_time}
                                    onChange={(e) =>
                                        setForm((current) => ({
                                            ...current,
                                            pickup: {
                                                ...current.pickup,
                                                close_time: e.target.value,
                                            },
                                        }))
                                    }
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Où le coursier demande le colis">
                                <input
                                    value={form.pickup.location}
                                    onChange={(e) =>
                                        setForm((current) => ({
                                            ...current,
                                            pickup: {
                                                ...current.pickup,
                                                location: e.target.value,
                                            },
                                        }))
                                    }
                                    maxLength={80}
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    )}
                    <label className="flex cursor-pointer items-center gap-2 text-xs text-ink">
                        <input
                            type="checkbox"
                            checked={form.notify_buyer}
                            onChange={(e) =>
                                setForm((current) => ({
                                    ...current,
                                    notify_buyer: e.target.checked,
                                }))
                            }
                            className="h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                        />
                        DHL envoie aussi son suivi par e-mail à l’acheteur
                    </label>
                </section>

                {check && (
                    <div
                        className={`space-y-1 rounded-lg px-3 py-2.5 text-xs leading-relaxed ${
                            check.ok
                                ? 'bg-emerald-50 text-emerald-800'
                                : 'bg-rose-50 text-rose-800'
                        }`}
                    >
                        <p className="flex gap-1.5 font-medium">
                            {check.ok ? (
                                <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            ) : (
                                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            )}
                            {check.message}
                        </p>
                        {check.details.map((detail) => (
                            <p key={detail}>• {detail}</p>
                        ))}
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Retour
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy || incomplete}
                        onClick={runCheck}
                    >
                        Vérifier avec DHL
                    </Button>
                    <Button
                        type="button"
                        disabled={busy || incomplete}
                        onClick={submit}
                    >
                        {busy ? 'Envoi…' : 'Créer l’expédition'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
