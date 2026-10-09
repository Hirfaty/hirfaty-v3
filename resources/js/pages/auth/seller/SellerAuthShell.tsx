import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

/**
 * Shared chrome for the artisan signup flow — /sell, its code screen and the
 * form that opens the workshop.
 *
 * Split-screen rather than the centred card the shopper flow uses (AuthShell).
 * The two are answering different questions: /login is a task someone already
 * decided to do, while /sell has to persuade first, and a full-bleed
 * photograph of the work is the argument. Keeping them visibly different is
 * the point — a seller should be able to tell at a glance they are not on the
 * shopper's door.
 *
 * The photograph sits on the *trailing* edge (`order-last`), which mirrors to
 * the left under `dir="rtl"` along with everything else — logical properties
 * throughout, no hardcoded left/right.
 */
export default function SellerAuthShell({
    image,
    quote,
    title,
    titleAccent,
    subtitle,
    children,
    footer,
    /**
     * Widens the form column for the one screen that needs it. The details
     * form holds nine fields across two sections and reads cramped at the
     * width that suits a single email input.
     */
    wide = false,
}: {
    image: string;
    /** Overlaid on the photograph — the pitch, in one line. */
    quote: string;
    title: string;
    titleAccent: string;
    subtitle: string;
    children: ReactNode;
    footer?: ReactNode;
    wide?: boolean;
}) {
    const { t, locale } = useTranslation();

    const switchLocale = (code: string) =>
        router.post(route('locale.switch', code), {}, { preserveScroll: true });

    return (
        <>
            <Head title={t('seller.join.page_title')} />

            {/*
             * The page itself scrolls and the photograph is `sticky`, so it
             * stays pinned beside the form however long the form gets.
             */}
            <div className="min-h-screen bg-souk-paper lg:grid lg:grid-cols-[1.05fr_0.95fr]">
                {/* ── Form column ─────────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-14 lg:py-8">
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

                    <div className="flex flex-1 flex-col justify-center py-10">
                        <div
                            className={`w-full ${wide ? 'max-w-xl' : 'max-w-md'}`}
                        >
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

                            {/* Hardcoded, not translated: "Hirfaty Seller" is a
                                product name, and product names stay in Latin
                                script in every locale — same rule the account
                                cards on /login follow. */}
                            <p className="mt-6 text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                Hirfaty Seller
                            </p>

                            <h1 className="mt-3 font-display text-[clamp(1.6rem,3.2vw,2.3rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                                {title}{' '}
                                <span className="text-souk-brown">
                                    {titleAccent}
                                </span>
                            </h1>

                            <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/70">
                                {subtitle}
                            </p>

                            {children}
                        </div>
                    </div>

                    {footer && (
                        <div
                            className={`w-full ${wide ? 'max-w-xl' : 'max-w-md'}`}
                        >
                            {footer}
                        </div>
                    )}
                </main>

                {/* ── Photograph ──────────────────────────────────────── */}
                {/*
                 * `order-last` puts it on the trailing edge; hidden below `lg`
                 * so a phone gets the form rather than a half-screen of
                 * decoration above it. Decorative, hence empty alt.
                 * `self-start` keeps the grid from stretching it to the form's
                 * height, which would leave `sticky` nothing to stick within.
                 */}
                <aside className="sticky top-0 order-last hidden h-screen self-start overflow-hidden bg-souk-brown lg:block">
                    <img
                        src={image}
                        alt=""
                        className="h-full w-full object-cover"
                    />

                    {/* <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.10] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '320px',
                        }}
                    /> */}

                    {/* Bottom-weighted scrim so the quote stays legible over
                        whatever the photograph happens to be doing there. */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 bg-gradient-to-t from-souk-brown-deep/85 via-souk-brown-deep/20 to-transparent"
                    />

                    <p className="absolute inset-x-0 bottom-0 p-12 font-display text-2xl leading-snug text-souk-cream">
                        {quote}
                    </p>
                </aside>
            </div>
        </>
    );
}
