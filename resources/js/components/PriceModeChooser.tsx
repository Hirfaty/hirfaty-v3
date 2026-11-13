import { usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ArrowRight, Building2, Check, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { switchPriceMode, usePriceMode } from '@/hooks/use-price-mode';
import type { PriceMode } from '@/hooks/use-price-mode';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Pages where a "who are you?" popup would get in the way of what the visitor
 * came to do: signing in, paying, reading an error.
 */
const QUIET_PAGES = ['auth/', 'buyer/checkout', 'error'];

/** Let the page paint first, so the question lands on something. */
const OPEN_DELAY_MS = 700;

/**
 * The first-visit question: "particulier" or "professionnel"?
 *
 * Asked once — the answer lives in a year-long cookie (PriceModeService), and
 * the header switch changes it afterwards. Closing the popup without picking
 * counts as "particulier", the price list everyone sees by default, so it
 * never asks twice.
 *
 * Either answer is final for checkout too: there is no trade account to
 * apply for — on the pro list each piece is simply sold from the minimum
 * quantity the admin set on it. So one click picks, closes, and the prices
 * behind the popup change in place.
 *
 * Mounted by SiteHeader, the one thing every storefront page shares.
 */
export default function PriceModeChooser() {
    const { t } = useTranslation();
    const page = usePage();
    const { needs_choice } = usePriceMode();
    const [open, setOpen] = useState(false);

    const quiet = QUIET_PAGES.some((prefix) =>
        page.component.startsWith(prefix),
    );

    useEffect(() => {
        if (!needs_choice || quiet) {
            return;
        }

        const timer = window.setTimeout(() => setOpen(true), OPEN_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [needs_choice, quiet]);

    /*
     * `preserveState` so the popup can play its closing animation over the
     * page while the reload brings the new prices in underneath.
     */
    const choose = (mode: PriceMode) => {
        setOpen(false);
        switchPriceMode(mode, { preserveState: true });
    };

    const onOpenChange = (next: boolean) => {
        // Dismissed without picking: settle on the default so we don't ask again.
        if (!next) {
            choose('individual');

            return;
        }

        setOpen(next);
    };

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-souk-title/55 backdrop-blur-[3px]" />
                <DialogPrimitive.Content className="fixed top-1/2 left-1/2 z-50 max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl border border-souk-brown/10 bg-souk-paper px-5 pt-9 pb-7 text-center shadow-2xl shadow-souk-title/30 duration-300 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:px-10">
                    <DialogPrimitive.Close
                        aria-label={t('pro.chooser.close')}
                        className="absolute end-3 top-3 rounded-full p-1.5 text-souk-brown-deep/40 transition-colors hover:bg-souk-brown/10 hover:text-souk-title"
                    >
                        <X className="h-4 w-4" strokeWidth={1.75} />
                    </DialogPrimitive.Close>

                    {/* The arch-topped badge the header icons wear. */}
                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-t-full rounded-b-lg bg-souk-peach text-souk-brown ring-1 ring-souk-brown/15">
                        <Check className="h-5 w-5" strokeWidth={2} />
                    </span>

                    <DialogPrimitive.Title className="mt-5 font-display text-[1.75rem] leading-tight font-bold text-souk-title sm:text-3xl">
                        {t('pro.chooser.title')}
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="mx-auto mt-2.5 max-w-sm text-[15px] leading-relaxed text-souk-brown-deep/70">
                        {t('pro.chooser.lead')}
                    </DialogPrimitive.Description>

                    <div className="mt-7 space-y-3">
                        <Choice
                            icon={Building2}
                            title={t('pro.chooser.professional.title')}
                            hint={t('pro.chooser.professional.hint')}
                            onSelect={() => choose('professional')}
                        />
                        <Choice
                            icon={UserRound}
                            title={t('pro.chooser.individual.title')}
                            hint={t('pro.chooser.individual.hint')}
                            onSelect={() => choose('individual')}
                        />
                    </div>

                    <p className="mt-6 text-[13px] text-souk-brown-deep/60">
                        {t('pro.chooser.footnote')}
                    </p>
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}

/** One answer, as a whole clickable row. */
function Choice({
    icon: Icon,
    title,
    hint,
    onSelect,
}: {
    icon: typeof Building2;
    title: string;
    hint: string;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className="group flex w-full items-center gap-4 rounded-xl border border-souk-brown/15 bg-souk-peach/50 px-4 py-4 text-start transition-colors duration-200 hover:border-souk-brown/40 hover:bg-souk-peach focus-visible:ring-2 focus-visible:ring-souk-brown focus-visible:ring-offset-2 focus-visible:outline-none sm:px-5"
        >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-souk-brown/10 text-souk-brown transition-colors duration-200 group-hover:bg-souk-brown group-hover:text-souk-cream">
                <Icon className="h-5 w-5" strokeWidth={1.6} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-display text-[17px] leading-snug text-souk-title">
                    {title}
                </span>
                <span className="mt-0.5 block text-sm text-souk-brown-deep/65">
                    {hint}
                </span>
            </span>
            <ArrowRight
                className="h-4 w-4 shrink-0 text-souk-brown-deep/50 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-souk-brown rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                strokeWidth={1.75}
            />
        </button>
    );
}
