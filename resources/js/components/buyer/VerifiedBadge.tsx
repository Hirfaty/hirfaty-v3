import { BadgeCheck } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * "Vérifié par Hirfaty" — the mark that sits beside a shop's name.
 *
 * Backed by the shop approval an admin grants from /admin/stores
 * (StoreVerificationService), surfaced to the storefront as
 * `store.is_verified`. Render it only when that flag is true; this component
 * deliberately does not check anything itself, so there is one decision about
 * who is verified and it lives on the server.
 *
 * Zellige green rather than terracotta: the storefront's accent is already
 * terracotta, and a trust mark in the same colour reads as decoration. Green
 * at this value carries standing without being mistaken for a validation
 * tick — the same reasoning as the buyer account area (see app.css).
 *
 * `size="sm"` is the icon-only form for tight spots (a product card); the
 * default pairs it with the label.
 */
export default function VerifiedBadge({
    size = 'md',
    className = '',
}: {
    size?: 'sm' | 'md';
    className?: string;
}) {
    const { t } = useTranslation();
    const label = t('buyer.store.verified');

    if (size === 'sm') {
        // Wrapped rather than putting `title`/`aria-label` on the icon:
        // lucide forwards only SVG props, so the tooltip and the accessible
        // name have to live on an element that accepts them.
        return (
            <span
                role="img"
                aria-label={label}
                title={label}
                className={`inline-flex shrink-0 text-souk-green ${className}`}
            >
                <BadgeCheck className="h-4 w-4" strokeWidth={1.8} />
            </span>
        );
    }

    return (
        <span
            title={label}
            className={`inline-flex shrink-0 items-center gap-1 rounded-full bg-souk-green/10 px-2 py-0.5 text-[11px] font-medium text-souk-green ${className}`}
        >
            <BadgeCheck className="h-3.5 w-3.5" strokeWidth={1.8} />
            {label}
        </span>
    );
}
