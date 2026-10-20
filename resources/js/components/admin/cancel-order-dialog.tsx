import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
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

export type CancellationReason = { code: string; label: string };

/** What a refund would give back, when the orders were paid online. */
export type RefundOffer = {
    provider: 'payzone' | 'paypal';
    /** Refunded through the provider's API at once; false leaves an alert. */
    automatic: boolean;
    /** Already written in the charged currency ("22,08 €", "240,00 MAD"). */
    amount: string;
};

/**
 * The desk cancelling a purchase (or one workshop's part of it): why, picked
 * from reasons the buyer reads in their own language, plus the desk's own
 * words if needed — and, for an order paid online, whether the buyer gets
 * the money back. See Admin\OrderController::cancel and
 * OrderCancellationService.
 */
export default function CancelOrderDialog({
    open,
    onOpenChange,
    reference,
    scope,
    appliesTo,
    reasons,
    refund,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The order the request is made against (the whole group by default). */
    reference: string;
    scope: 'group' | 'single';
    /** "tout l’achat", or the workshop's name. */
    appliesTo: string;
    reasons: CancellationReason[];
    /** Null when nothing was paid online, so there is nothing to refund. */
    refund: RefundOffer | null;
}) {
    const [picked, setPicked] = useState<string[]>([]);
    const [details, setDetails] = useState('');
    // On by default: a cancelled order the buyer paid for is normally refunded.
    const [giveBack, setGiveBack] = useState(true);
    const [busy, setBusy] = useState(false);

    const toggle = (code: string) =>
        setPicked((current) =>
            current.includes(code)
                ? current.filter((c) => c !== code)
                : [...current, code],
        );

    const reset = () => {
        setPicked([]);
        setDetails('');
        setGiveBack(true);
    };

    const canSubmit = picked.length > 0 || details.trim() !== '';

    const submit = () => {
        setBusy(true);

        router.post(
            route('admin.orders.cancel', reference),
            {
                reasons: picked,
                details: details.trim() || null,
                refund: refund !== null && giveBack,
                scope,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (
                        page.props as {
                            flash?: { success?: string; error?: string };
                        }
                    ).flash;

                    if (flash?.error) {
                        toast.error(flash.error);
                    } else {
                        toast.success(
                            flash?.success ?? 'Annulation enregistrée.',
                        );
                    }

                    reset();
                    onOpenChange(false);
                },
                onError: (errors) =>
                    toast.error(
                        (Object.values(errors)[0] as string) ??
                            'Annulation impossible.',
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Annuler la commande</DialogTitle>
                    <DialogDescription>
                        S’applique à : {appliesTo}. L’acheteur reçoit un e-mail
                        avec les motifs, traduits dans sa langue.
                    </DialogDescription>
                </DialogHeader>

                <div>
                    <p className="text-xs font-medium text-ink">Motifs</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {reasons.map((reason) => {
                            const on = picked.includes(reason.code);

                            return (
                                <button
                                    key={reason.code}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => toggle(reason.code)}
                                    className={`rounded-full border px-3 py-1.5 text-xs transition ${
                                        on
                                            ? 'border-rose-300 bg-rose-50 font-medium text-rose-700'
                                            : 'border-border text-ink-soft hover:border-ink/30 hover:text-ink'
                                    }`}
                                >
                                    {reason.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <label className="block">
                    <span className="mb-1.5 block text-xs font-medium text-ink">
                        Précisions (facultatif)
                    </span>
                    <textarea
                        value={details}
                        onChange={(e) => setDetails(e.target.value)}
                        rows={3}
                        maxLength={500}
                        placeholder="Ex. : l’artisan relance une fournée le mois prochain."
                        className="w-full resize-none rounded-lg border border-border bg-card px-3 py-2.5 text-sm text-ink transition placeholder:text-ink-soft/60 focus:border-ink focus:ring-2 focus:ring-ink/10 focus:outline-none"
                    />
                    <span className="mt-1 block text-xs text-ink-soft">
                        Envoyées telles quelles, sans traduction.
                    </span>
                </label>

                {refund && (
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/30 p-3.5">
                        <input
                            type="checkbox"
                            checked={giveBack}
                            onChange={(e) => setGiveBack(e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-border text-ink focus:ring-ink/20"
                        />
                        <span className="text-sm">
                            <span className="flex items-center gap-1.5 font-medium text-ink">
                                <RotateCcw className="h-3.5 w-3.5" />
                                Rembourser l’acheteur — {refund.amount}
                            </span>
                            <span className="mt-1 block text-xs leading-relaxed text-ink-soft">
                                {!giveBack
                                    ? 'L’acheteur sera informé que cette annulation ne donne pas lieu à un remboursement.'
                                    : !refund.automatic
                                      ? 'Remboursement à faire depuis le back-office Payzone : une alerte sera créée pour le rappeler.'
                                      : refund.provider === 'paypal'
                                        ? 'Remboursé tout de suite sur son compte PayPal.'
                                        : 'Remboursé tout de suite sur sa carte, via Payzone.'}
                            </span>
                        </span>
                    </label>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Retour
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={!canSubmit || busy}
                        onClick={submit}
                    >
                        {busy ? 'Annulation…' : 'Confirmer l’annulation'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
