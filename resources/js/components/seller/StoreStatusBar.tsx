import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Clock, FileWarning } from 'lucide-react';
import type { SellerStoreStatus } from '@/types';

/** Human labels for the profile fields Store::REQUIRED_PROFILE_FIELDS names. */
const FIELD_LABELS: Record<string, string> = {
    store_name: 'le nom de l’atelier',
    category_id: 'le métier',
    city_id: 'la ville',
    description: 'la présentation',
};

/**
 * The standing explanation of why a seller cannot publish yet.
 *
 * Sticky rather than a dismissible toast: this is not an event, it is a state
 * the seller is in until they act on it — and the thing it blocks ("Ajouter un
 * produit") is missing from the sidebar the whole time, which is confusing
 * without a permanent reason on screen.
 *
 * Renders nothing once the shop can sell, so the chrome disappears the moment
 * it stops being true.
 */
export default function StoreStatusBar({
    store,
}: {
    store: SellerStoreStatus;
}) {
    if (store.can_sell) {
        return null;
    }

    // Incomplete beats rejected beats pending: an admin cannot review what is
    // not filled in, so that is always the actionable next step.
    const variant = !store.missing_fields.length
        ? store.verification_status === 'rejected'
            ? 'rejected'
            : 'pending'
        : 'incomplete';

    const config = {
        incomplete: {
            Icon: FileWarning,
            wrap: 'border-amber-300/70 bg-amber-50 text-amber-900',
            iconClass: 'text-amber-600',
            title: 'Votre atelier n’est pas encore complet',
            body: `Il manque ${store.missing_fields
                .map((f) => FIELD_LABELS[f] ?? f)
                .join(
                    ', ',
                )}. Vos produits ne peuvent pas être mis en ligne tant que votre atelier n’a pas été validé par Hirfaty.`,
            cta: 'Compléter mon atelier',
        },
        pending: {
            Icon: Clock,
            wrap: 'border-sky-300/70 bg-sky-50 text-sky-900',
            iconClass: 'text-sky-600',
            title: 'Votre atelier est en cours de validation',
            body: 'Notre équipe le relit — comptez un à deux jours ouvrés. Vous pourrez ajouter des produits dès qu’il sera approuvé.',
            cta: 'Voir mon atelier',
        },
        rejected: {
            Icon: AlertTriangle,
            wrap: 'border-red-300/70 bg-red-50 text-red-900',
            iconClass: 'text-red-600',
            title: 'Votre atelier n’a pas été validé',
            body:
                store.rejection_reason ??
                'Corrigez les informations de votre atelier, puis renvoyez-le en validation.',
            cta: 'Corriger et renvoyer',
        },
    }[variant];

    const { Icon } = config;

    return (
        <div
            role="status"
            className={`sticky top-14 z-20 border-b px-4 py-3 md:px-8 ${config.wrap}`}
        >
            <div className="flex flex-wrap items-start gap-x-3 gap-y-2">
                <Icon
                    className={`mt-0.5 h-5 w-5 shrink-0 ${config.iconClass}`}
                    strokeWidth={1.8}
                />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">{config.title}</p>
                    <p className="mt-0.5 text-sm opacity-90">{config.body}</p>
                </div>
                <Link
                    href={route('seller.settings.index')}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-current/25 bg-white/60 px-3.5 py-2 text-sm font-medium transition-colors hover:bg-white"
                >
                    {config.cta}
                    <ArrowRight
                        className="h-3.5 w-3.5 rtl:rotate-180"
                        strokeWidth={1.8}
                    />
                </Link>
            </div>
        </div>
    );
}
