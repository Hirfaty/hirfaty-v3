import { useMemo } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

interface PasswordStrengthMeterProps {
    password: string;
    className?: string;
}

/**
 * One check per rule in Password::defaults() (App\Providers\AppServiceProvider)
 * — min 12 chars, both cases, a number, a symbol. Four checks, four bars, and
 * "all lit" means the password actually clears registration. A meter that
 * scored differently from what the server enforces would tell the user
 * they're done when they're not.
 */
const CHECKS: Array<(password: string) => boolean> = [
    (password) => password.length >= 12,
    (password) => /[a-z]/.test(password) && /[A-Z]/.test(password),
    (password) => /[0-9]/.test(password),
    (password) => /[^a-zA-Z0-9]/.test(password),
];

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
        () => CHECKS.filter((check) => check(password)).length,
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
                {CHECKS.map((_, i) => (
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
                {t(`common.auth.register.password_strength_${level.key}`)}
            </p>
        </div>
    );
}
