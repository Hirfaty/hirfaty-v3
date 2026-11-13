import { switchPriceMode, usePriceMode } from '@/hooks/use-price-mode';
import type { PriceMode } from '@/hooks/use-price-mode';
import { useTranslation } from '@/hooks/use-translation';

const MODES: PriceMode[] = ['individual', 'professional'];

/**
 * The header's "Particulier | Pro" switch: which price list the visitor
 * shops on (PriceModeService) — shown and billed alike. Open to anyone; on
 * the pro side each piece is sold from its minimum quantity.
 *
 * `bar` is the compact pill in the desktop header (`light` over a dark hero),
 * from `xl` up — below that the bar has no room left for it, and the same
 * choice sits in the language/currency menu instead (RegionMenu). `menu` is
 * the full-width version in the phone drawer, with a line under each side
 * saying what it means.
 */
export default function PriceModeSwitch({
    variant = 'bar',
    light = false,
    onSwitched,
}: {
    variant?: 'bar' | 'menu';
    light?: boolean;
    /** Lets the phone drawer close itself once a side is picked. */
    onSwitched?: () => void;
}) {
    const { t } = useTranslation();
    const { mode } = usePriceMode();

    const pick = (next: PriceMode) => {
        onSwitched?.();

        if (next !== mode) {
            switchPriceMode(next);
        }
    };

    if (variant === 'menu') {
        return (
            <div
                role="radiogroup"
                aria-label={t('pro.mode.aria')}
                className="grid grid-cols-2 gap-2"
            >
                {MODES.map((m) => {
                    const active = mode === m;

                    return (
                        <button
                            key={m}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => pick(m)}
                            className={`rounded-lg px-3 py-2.5 text-start transition-colors ${
                                active
                                    ? 'bg-souk-brown text-souk-cream'
                                    : 'bg-sand/30 text-ink/75 hover:bg-sand/50 hover:text-ink'
                            }`}
                        >
                            <span className="block text-sm font-medium">
                                {t(`pro.mode.${m}`)}
                            </span>
                            <span
                                className={`mt-0.5 block text-[11px] leading-snug ${active ? 'text-souk-cream/75' : 'text-ink/50'}`}
                            >
                                {t(`pro.mode.${m}_hint`)}
                            </span>
                        </button>
                    );
                })}
            </div>
        );
    }

    return (
        <div
            role="radiogroup"
            aria-label={t('pro.mode.aria')}
            title={t('pro.mode.label')}
            className={`hidden items-center rounded-full p-0.5 xl:inline-flex ${
                light
                    ? 'bg-white/10 ring-1 ring-white/30'
                    : 'bg-sand/35 ring-1 ring-souk-brown/15'
            }`}
        >
            {MODES.map((m) => {
                const active = mode === m;

                return (
                    <button
                        key={m}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        title={t(`pro.mode.${m}_hint`)}
                        onClick={() => pick(m)}
                        className={`cursor-pointer rounded-full px-3 py-1 text-[11px] font-medium tracking-[0.06em] whitespace-nowrap transition-colors ${
                            active
                                ? light
                                    ? 'bg-white text-souk-title shadow-sm'
                                    : 'bg-souk-brown text-souk-cream shadow-sm'
                                : light
                                  ? 'text-white/80 hover:text-white'
                                  : 'text-ink/65 hover:text-ink'
                        }`}
                    >
                        {t(`pro.mode.${m}`)}
                    </button>
                );
            })}
        </div>
    );
}
