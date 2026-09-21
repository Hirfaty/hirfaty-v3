import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import { Section } from './form';

const LOCALES = [
    { code: 'fr', label: 'Français' },
    { code: 'ar', label: 'العربية' },
    { code: 'en', label: 'English' },
] as const;

/**
 * Language and display currency, as account settings.
 *
 * The same two choices the header's RegionMenu offers, in the place a buyer
 * looks when they want to change something permanently rather than glance at
 * a price. Both write to the same session keys, so setting one here and the
 * other from the header cannot disagree.
 *
 * Neither is stored on the user record. Currency in particular is deliberately
 * a session preference with an IP-derived default: a buyer who has never
 * chosen sees their region's currency automatically, and only the act of
 * picking one pins it. Persisting a column would mean a buyer who travels
 * keeps seeing the currency of wherever they first signed up.
 */
export default function PreferencesSection() {
    const { t, locale } = useTranslation();
    const { currency, options } = useCurrency();

    const go = (routeName: string, code: string) =>
        router.post(route(routeName, code), {}, { preserveScroll: true });

    const Chip = ({
        label,
        active,
        onSelect,
    }: {
        label: string;
        active: boolean;
        onSelect: () => void;
    }) => (
        <button
            type="button"
            onClick={onSelect}
            aria-current={active ? 'true' : undefined}
            className={`inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-2 text-sm transition-colors ${
                active
                    ? 'border-terracotta bg-terracotta/10 text-terracotta'
                    : 'border-souk-brown/20 text-souk-brown-deep hover:border-souk-brown/45'
            }`}
        >
            {active && <Check className="h-3.5 w-3.5" />}
            {label}
        </button>
    );

    return (
        <Section
            title={t('buyer.account.preferences_title')}
            description={t('buyer.account.preferences_subtitle')}
        >
            <div className="space-y-6">
                <div>
                    <p className="mb-2.5 text-xs tracking-[0.16em] text-souk-brown-deep/60 uppercase">
                        {t('common.language_label')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {LOCALES.map((l) => (
                            <Chip
                                key={l.code}
                                label={l.label}
                                active={locale === l.code}
                                onSelect={() => go('locale.switch', l.code)}
                            />
                        ))}
                    </div>
                </div>

                <div>
                    <p className="mb-2.5 text-xs tracking-[0.16em] text-souk-brown-deep/60 uppercase">
                        {t('common.currency_label')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {options.map((c) => (
                            <Chip
                                key={c.code}
                                label={
                                    c.symbol === c.code
                                        ? c.code
                                        : `${c.code} ${c.symbol}`
                                }
                                active={currency.code === c.code}
                                onSelect={() => go('currency.switch', c.code)}
                            />
                        ))}
                    </div>

                    <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/60">
                        {currency.is_base
                            ? t('buyer.account.currency_auto')
                            : t('common.currency_note').replace(
                                  ':currency',
                                  currency.code,
                              )}
                    </p>
                </div>
            </div>
        </Section>
    );
}
