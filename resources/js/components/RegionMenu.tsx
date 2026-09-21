import { router } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';

const LOCALES = [
    { code: 'fr', label: 'Français' },
    { code: 'ar', label: 'العربية' },
    { code: 'en', label: 'English' },
] as const;

/**
 * Language and currency, behind one button.
 *
 * They were two separate controls in the header — three language tiles and a
 * seven-option `<select>` — which is a lot of chrome for two preferences a
 * visitor sets once. Folding them into a single "region" popover also puts
 * them in the order they are actually decided: what language am I reading,
 * and what money am I reading it in.
 *
 * Both post to their own endpoint and reload, so the choice lands in the
 * session and every price and label on the page re-renders from the server.
 */
export default function RegionMenu({ light = false }: { light?: boolean }) {
    const { t, locale } = useTranslation();
    const { currency, options } = useCurrency();
    const [open, setOpen] = useState(false);

    const go = (routeName: string, code: string) => {
        setOpen(false);
        router.post(route(routeName, code), {}, { preserveScroll: true });
    };

    /** One row in either list — a label, and a tick when it is the active one. */
    const Row = ({
        label,
        hint,
        active,
        onSelect,
    }: {
        label: string;
        hint?: string;
        active: boolean;
        onSelect: () => void;
    }) => (
        <button
            type="button"
            onClick={onSelect}
            aria-current={active ? 'true' : undefined}
            className={`flex w-full items-center justify-between gap-3 rounded-md px-2.5 py-2 text-start text-sm transition-colors ${
                active
                    ? 'bg-terracotta/10 text-terracotta'
                    : 'text-ink hover:bg-sand/50'
            }`}
        >
            <span className="flex items-baseline gap-2">
                {label}
                {hint && (
                    <span className="text-[11px] text-ink-soft">{hint}</span>
                )}
            </span>
            {active && <Check className="h-3.5 w-3.5 shrink-0" />}
        </button>
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label={t('common.region_label')}
                    title={t('common.region_label')}
                    className={`hidden items-center gap-1.5 rounded-sm px-2 py-1 text-[11px] font-medium tracking-[0.14em] transition-colors md:inline-flex ${
                        light
                            ? 'text-white/75 hover:bg-white/15 hover:text-white'
                            : 'text-ink/65 hover:bg-sand/40 hover:text-ink'
                    }`}
                >
                    <Globe className="h-3.5 w-3.5" strokeWidth={1.7} />
                    {/*
                     * The trigger states the current pair, so the visitor can
                     * see what they are on without opening anything.
                     */}
                    {String(locale).toUpperCase()} · {currency.code}
                </button>
            </PopoverTrigger>

            <PopoverContent
                align="end"
                className="w-60 rounded-xl border-souk-brown/15 p-2"
            >
                <p className="px-2.5 pt-1 pb-1.5 text-[10px] tracking-[0.16em] text-ink-soft uppercase">
                    {t('common.language_label')}
                </p>
                <div className="space-y-0.5">
                    {LOCALES.map((l) => (
                        <Row
                            key={l.code}
                            label={l.label}
                            active={locale === l.code}
                            onSelect={() => go('locale.switch', l.code)}
                        />
                    ))}
                </div>

                <div className="my-2 h-px bg-souk-brown/10" />

                <p className="px-2.5 pb-1.5 text-[10px] tracking-[0.16em] text-ink-soft uppercase">
                    {t('common.currency_label')}
                </p>
                <div className="max-h-56 space-y-0.5 overflow-y-auto">
                    {options.map((c) => (
                        <Row
                            key={c.code}
                            label={c.code}
                            hint={c.symbol === c.code ? undefined : c.symbol}
                            active={currency.code === c.code}
                            onSelect={() => go('currency.switch', c.code)}
                        />
                    ))}
                </div>

                {/*
                 * Says plainly that a converted price is a guide, not the
                 * bill. Dropped for the home market, where it is noise.
                 */}
                {/* {!currency.is_base && (
                    <p className="mt-2 border-t border-souk-brown/10 px-2.5 pt-2 text-[11px] leading-relaxed text-ink-soft">
                        {t('common.currency_note').replace(
                            ':currency',
                            currency.code,
                        )}
                    </p>
                )} */}
            </PopoverContent>
        </Popover>
    );
}
