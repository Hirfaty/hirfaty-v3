import { BadgePercent } from 'lucide-react';
import { switchPriceMode, usePriceMode } from '@/hooks/use-price-mode';
import { useTranslation } from '@/hooks/use-translation';

/**
 * "Pro prices shown — each piece has a minimum quantity."
 *
 * Above a grid while the visitor shops on the pro list (PriceModeService), so
 * the one condition attached to those prices is said before they meet it on a
 * product page. Offers the way back to personal prices, from one piece.
 * Renders nothing in individual mode.
 */
export default function ProPriceNotice({
    className = '',
}: {
    className?: string;
}) {
    const { t } = useTranslation();
    const { isPro } = usePriceMode();

    if (!isPro) {
        return null;
    }

    return (
        <div
            role="note"
            className={`flex flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-md border border-souk-brown/15 bg-souk-peach/70 px-4 py-3 sm:px-5 ${className}`}
        >
            <p className="flex min-w-[16rem] flex-1 items-start gap-3 text-[13.5px] leading-relaxed text-souk-brown-deep/85">
                <BadgePercent
                    className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                    strokeWidth={1.6}
                />
                <span>
                    <span className="font-medium text-souk-title">
                        {t('pro.notice.title')}
                    </span>{' '}
                    {t('pro.notice.body')}
                </span>
            </p>
            <button
                type="button"
                onClick={() => switchPriceMode('individual')}
                className="ps-7 text-xs text-souk-brown-deep/70 underline underline-offset-4 transition-colors hover:text-souk-title"
            >
                {t('pro.notice.show_retail')}
            </button>
        </div>
    );
}
