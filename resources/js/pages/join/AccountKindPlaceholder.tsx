import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import { login } from '@/routes';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

/**
 * Holding page for the two account kinds that are not yet built — Hirfaty
 * Seller and Hirfaty Business, at /sell and /business.
 *
 * The entry points on /login are real and settled; what sits behind them is
 * not. A stub rather than a half-built form on purpose: an artisan who lands
 * here should be told plainly that signup is not open yet and be given
 * somewhere to go, not walked into a flow that cannot finish.
 *
 * Deliberately does not use AuthShell — these pages are not part of the
 * shopper flow, and the "back to signup" link below points out of them.
 */
export default function AccountKindPlaceholder({
    brand,
    title,
    body,
    Icon,
}: {
    /** Untranslated product name, e.g. "Seller" in "Hirfaty Seller". */
    brand: string;
    title: string;
    body: string;
    Icon: LucideIcon;
}) {
    const { t, locale } = useTranslation();

    const switchLocale = (code: string) =>
        router.post(route('locale.switch', code), {}, { preserveScroll: true });

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col bg-souk-paper px-5 py-6 sm:px-8">
                <div className="flex items-center justify-between gap-4">
                    <Link
                        href={route('home')}
                        className="group inline-flex items-center gap-2 text-sm text-souk-brown-deep/70 transition-colors hover:text-souk-brown"
                    >
                        <ArrowLeft
                            className="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180"
                            strokeWidth={1.5}
                        />
                        <span className="hidden sm:inline">
                            {t('common.auth.login.back_home')}
                        </span>
                    </Link>

                    <div className="flex items-center text-[11px] font-medium tracking-[0.18em] text-souk-brown-deep/50">
                        {locales.map((l) => (
                            <button
                                key={l.code}
                                type="button"
                                onClick={() => switchLocale(l.code)}
                                className={`px-2 py-1 transition-colors ${
                                    locale === l.code
                                        ? 'text-souk-brown'
                                        : 'hover:text-souk-brown-deep'
                                }`}
                            >
                                {l.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-1 flex-col items-center justify-center py-8">
                    <Link
                        href={route('home')}
                        className="inline-flex items-center gap-2.5"
                        aria-label="Hirfaty"
                    >
                        <img
                            src={logo}
                            alt=""
                            className="h-7 w-7 object-contain"
                        />
                        <span className="font-display text-lg text-souk-title">
                            Hirfaty
                            <span className="text-souk-brown">.</span>
                        </span>
                    </Link>

                    <div className="mt-6 w-full max-w-md rounded-2xl border border-souk-brown/12 bg-white/70 p-8 text-center shadow-sm shadow-souk-brown/5">
                        <span className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-souk-brown/10">
                            <Icon
                                className="h-6 w-6 text-souk-brown"
                                strokeWidth={1.5}
                            />
                        </span>

                        <p className="mt-5 font-display text-2xl text-souk-title">
                            Hirfaty{' '}
                            <span className="text-souk-brown">{brand}</span>
                        </p>

                        <span className="mt-3 inline-flex items-center gap-1.5 rounded-full bg-souk-peach px-3 py-1 text-[11px] tracking-[0.15em] text-souk-brown uppercase">
                            <Clock className="h-3 w-3" strokeWidth={2} />
                            {t('common.auth.login.placeholder_soon')}
                        </span>

                        <h1 className="mt-5 font-display text-xl leading-snug text-souk-title">
                            {title}
                        </h1>

                        <p className="mt-2.5 text-sm leading-relaxed text-souk-brown-deep/70">
                            {body}
                        </p>

                        <Link
                            href={route('contact')}
                            className="mt-6 inline-flex w-full items-center justify-center rounded-full bg-souk-brown px-6 py-3.5 font-display text-base text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep"
                        >
                            {t('common.auth.login.placeholder_contact')}
                        </Link>
                    </div>

                    <p className="mt-5 text-center text-sm text-souk-brown-deep/65">
                        <Link
                            href={login()}
                            className="text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                        >
                            {t('common.auth.login.placeholder_back')}
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
