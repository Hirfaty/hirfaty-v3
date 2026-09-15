import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Stands in for a section's content while it is being built.
 *
 * Deliberately quiet — a rule, an icon and a line — so an unfinished page
 * reads as "nothing here yet" rather than as a broken one.
 */
export default function Placeholder({ icon: Icon }: { icon: LucideIcon }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col items-center justify-center px-6 py-24 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-souk-green/10">
                <Icon
                    className="h-6 w-6 text-souk-green/60"
                    strokeWidth={1.4}
                />
            </span>
            <p className="mt-5 text-base text-souk-brown-deep/55">
                {t('buyer.account.placeholder')}
            </p>
        </div>
    );
}
