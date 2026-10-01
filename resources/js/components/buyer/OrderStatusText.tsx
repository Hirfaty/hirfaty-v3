import { useTranslation } from '@/hooks/use-translation';
import type { OrderStatus } from '@/types/orders';

/**
 * Status → colour, for the text and the dot beside it. Everyday signal
 * colours rather than the brand's browns, so a status reads at a glance the
 * way it does on any shop: amber waiting, blue confirmed, orange on its way,
 * green arrived, red cancelled. The -600/-700 shades keep each one above 4.5:1
 * on the white cards.
 */
export const STATUS_COLOURS: Record<
    OrderStatus,
    { text: string; dot: string }
> = {
    pending: { text: 'text-amber-700', dot: 'bg-amber-500' },
    confirmed: { text: 'text-sky-700', dot: 'bg-sky-500' },
    shipped: { text: 'text-orange-600', dot: 'bg-orange-500' },
    delivered: { text: 'text-emerald-700', dot: 'bg-emerald-500' },
    cancelled: { text: 'text-rose-600', dot: 'bg-rose-500' },
};

export default function OrderStatusText({
    status,
    className = '',
}: {
    status: OrderStatus;
    className?: string;
}) {
    const { t } = useTranslation();
    const colours = STATUS_COLOURS[status] ?? STATUS_COLOURS.pending;

    return (
        <span
            className={`inline-flex items-center gap-1.5 font-semibold ${colours.text} ${className}`}
        >
            <span
                aria-hidden
                className={`h-2 w-2 shrink-0 rounded-full ${colours.dot}`}
            />
            {t(`buyer.orders.status_${status}`)}
        </span>
    );
}
