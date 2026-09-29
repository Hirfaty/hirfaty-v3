import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import type { PageProps } from '@/types';

/**
 * The thank-you that greets a buyer right after checkout.
 *
 * Opened by the one-shot `flash.order_placed` that CheckoutController sets on
 * its redirect here, so it shows once per purchase: a reload, or coming back
 * to the orders page later, finds no flash and renders nothing.
 *
 * "View my order" only closes it — the new purchase is the first card on the
 * page underneath, so there is nowhere further to go.
 */
export default function OrderPlacedDialog() {
    const { t } = useTranslation();
    const placed = usePage<PageProps>().props.flash?.order_placed ?? null;
    const [open, setOpen] = useState(placed !== null);

    if (!placed) {
        return null;
    }

    const [emailBefore, emailAfter = ''] = t('buyer.order_placed.email').split(
        ':email',
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="bg-souk-paper text-center sm:max-w-md">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-olive/15">
                    <CheckCircle2 className="h-7 w-7 text-olive" />
                </div>

                <DialogHeader className="items-center text-center sm:text-center">
                    <DialogTitle className="font-display text-2xl text-souk-title">
                        {t('buyer.order_placed.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed text-souk-title/65">
                        {t('buyer.order_placed.body')}
                    </DialogDescription>
                </DialogHeader>

                {placed.reference && (
                    <div className="rounded-xl border border-souk-brown/15 bg-white/60 px-4 py-3">
                        <p className="text-[11px] tracking-[0.12em] text-souk-title/50 uppercase">
                            {t('buyer.order_placed.reference')}
                        </p>
                        <p
                            dir="ltr"
                            className="mt-0.5 font-mono text-base font-semibold tracking-wide text-souk-title"
                        >
                            {placed.reference}
                        </p>
                    </div>
                )}

                {placed.email && (
                    <p className="text-xs text-souk-title/55">
                        {/* The address is isolated as LTR so, in Arabic, the
                            sentence's full stop does not jump to its far end. */}
                        {emailBefore}
                        <bdi dir="ltr">{placed.email}</bdi>
                        {emailAfter}
                    </p>
                )}

                <div className="mt-1 flex flex-col gap-2">
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="inline-flex w-full cursor-pointer items-center justify-center rounded-xl bg-souk-brown px-6 py-3 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep"
                    >
                        {t('buyer.order_placed.view')}
                    </button>
                    <Link
                        href={route('products.index')}
                        className="inline-flex w-full items-center justify-center rounded-xl px-6 py-2.5 text-sm font-medium text-souk-title/70 transition-colors hover:text-souk-title"
                    >
                        {t('buyer.order_placed.continue')}
                    </Link>
                </div>
            </DialogContent>
        </Dialog>
    );
}
