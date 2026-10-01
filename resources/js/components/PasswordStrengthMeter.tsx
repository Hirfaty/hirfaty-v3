import { useMemo } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { PASSWORD_CHECKS } from '@/lib/password-rules';
import { cn } from '@/lib/utils';

interface PasswordStrengthMeterProps {
    password: string;
    className?: string;
}

/**
 * Four checks, four bars, and "all lit" means the password actually clears
 * registration — the checks are shared with the pre-submit validation, so the
 * meter can never score differently from what the forms (and server) enforce.
 */
/** Index 0 is "typed something, meets none of the four rules yet". */
const LEVELS = [
    { key: 'very_weak', bar: 'bg-red-500', text: 'text-red-600' },
    { key: 'weak', bar: 'bg-orange-500', text: 'text-orange-600' },
    { key: 'fair', bar: 'bg-amber-500', text: 'text-amber-600' },
    { key: 'good', bar: 'bg-lime-500', text: 'text-lime-600' },
    { key: 'strong', bar: 'bg-emerald-500', text: 'text-emerald-600' },
] as const;

export default function PasswordStrengthMeter({
    password,
    className,
}: PasswordStrengthMeterProps) {
    const { t } = useTranslation();

    const score = useMemo(
        () => PASSWORD_CHECKS.filter((check) => check(password)).length,
        [password],
    );

    // Nothing typed yet — showing an all-empty meter would just be noise
    // before there's anything to judge.
    if (!password) {
        return null;
    }

    const level = LEVELS[score];

    return (
        <div
            className={cn(
                'mt-2 motion-safe:animate-in motion-safe:fade-in',
                className,
            )}
            aria-live="polite"
        >
            <div className="flex gap-1.5">
                {PASSWORD_CHECKS.map((_, i) => (
                    <div
                        key={i}
                        className="h-1.5 flex-1 overflow-hidden rounded-full bg-souk-brown/10"
                    >
                        <div
                            className={cn(
                                'h-full w-full origin-left rounded-full transition-transform duration-300 ease-out rtl:origin-right',
                                i < score ? level.bar : 'scale-x-0',
                            )}
                            style={{ transitionDelay: `${i * 60}ms` }}
                        />
                    </div>
                ))}
            </div>
            <p
                className={cn(
                    'mt-1.5 text-[11px] font-medium transition-colors duration-300',
                    level.text,
                )}
            >
                {t(`common.auth.login.password_strength_${level.key}`)}
            </p>
        </div>
    );
}
