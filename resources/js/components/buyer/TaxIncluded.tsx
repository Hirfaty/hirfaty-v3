import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * The small "(taxes included)" note set beside a product's price.
 *
 * Replaces the "TTC" suffix the prices used to carry: a buyer reads the
 * number on its own, with the tax mention kept deliberately quiet. Always in
 * the text cut, never the display one, so it stays legible at this size even
 * beside a large Didot price.
 */
export default function TaxIncluded({ className }: { className?: string }) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'ms-1 font-sans text-[10px] font-normal tracking-normal whitespace-nowrap text-souk-brown-deep/55 normal-case',
                className,
            )}
        >
            {t('common.tax_included')}
        </span>
    );
}
