import { Building2, UserRound } from 'lucide-react';

/** The price list a purchase was billed on — Order::price_mode. */
export type OrderPriceMode = 'individual' | 'professional' | null;

/**
 * Particulier or Entreprise, as the buyer chose at checkout
 * (PriceModeService). Renders nothing for purchases placed before the price
 * list was recorded, rather than guessing.
 */
export default function PriceModeBadge({
    mode,
    className = '',
}: {
    mode: OrderPriceMode;
    className?: string;
}) {
    if (mode === null) {
        return null;
    }

    const business = mode === 'professional';
    const Icon = business ? Building2 : UserRound;

    return (
        <span
            title={
                business
                    ? 'Acheté aux prix Entreprise (pro)'
                    : 'Acheté aux prix Particulier'
            }
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${
                business
                    ? 'bg-violet-100 text-violet-700'
                    : 'bg-slate-100 text-slate-700'
            } ${className}`}
        >
            <Icon className="h-3 w-3" />
            {business ? 'Entreprise' : 'Particulier'}
        </span>
    );
}
