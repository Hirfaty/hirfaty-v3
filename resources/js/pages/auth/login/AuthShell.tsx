import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import exploreArt from '@/images/categories/art.jpeg';
import exploreLantern from '@/images/categories/lantern.jpeg';
import explorePottery from '@/images/categories/pottery.jpeg';
import exploreTextile from '@/images/categories/textile.jpeg';
import logo from '@/images/logo.png';
import { switchLocale } from '@/lib/locale';

const locales = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'AR' },
    { code: 'en', label: 'EN' },
] as const;

/**
 * The little craft photographs under the card.
 *
 * Reuses the homepage category images rather than adding new assets — they are
 * already in the bundle and already the right subject matter. Purely
 * decorative, so they carry empty alt text and are hidden from the a11y tree.
 */
const THUMBNAILS = [
    explorePottery,
    exploreTextile,
    exploreLantern,
    exploreArt,
] as const;

/**
 * The signup branch, in order. `key` matches the `auth.login.step_*` keys.
 *
 * Only the signup branch has steps. A returning visitor's journey is email →
 * password (or email → code), which is not a three-stage process and must not
 * claim to be one — telling someone signing in that they are on "step 1 of 3"
 * is a promise of two more screens that never arrive.
 */
const STEPS = ['email', 'code', 'details'] as const;

export type SignupStep = (typeof STEPS)[number];

/**
 * Shared chrome for every screen of the unified /login flow — the email
 * field, the password screen, the code screens and profile completion.
 *
 * A single centered card rather than the old full-height split-screen brand
 * panel: these are short screens, and a half-page photograph next to one
 * field read as decoration competing with the task. Step 1 passes the fanned
 * photo deck as `aside`; everything else gets the small thumbnail strip
 * beneath the card instead.
 */
export default function AuthShell({
    step,
    title,
    titleAccent,
    subtitle,
    children,
    footer,
    aside,
    thumbnails = true,
    wide = false,
}: {
    /**
     * Which signup step this is, or `null` on the sign-in screens — which
     * suppresses the step indicator entirely rather than showing a misleading
     * one.
     */
    step: SignupStep | null;
    title: string;
    titleAccent: string;
    subtitle: string;
    children: ReactNode;
    footer?: ReactNode;
    /**
     * Optional panel shown beside the card on `lg` and up — the email step
     * passes the fanned photo deck. Supplying it widens the whole layout into
     * two columns and drops the small thumbnail strip, which would otherwise
     * be the same idea twice on one screen.
     */
    aside?: ReactNode;
    /**
     * Whether to show the little craft photographs beneath the card.
     * Independent of `aside` — a page can decline the thumbnails without
     * supplying a replacement, e.g. profile completion, which is already
     * dense with fields and does not need a decorative strip pulling the eye
     * further down.
     */
    thumbnails?: boolean;
    /**
     * A card with more to hold than a single field — profile completion's
     * two-column name/phone/password layout, for instance — reads cramped at
     * the same width as the one-field screens. `wide` steps the card up from
     * `max-w-md` (28rem) to `max-w-xl` (36rem) without going as far as the
     * two-column `aside` layout.
     */
    wide?: boolean;
}) {
    const { t, locale } = useTranslation();

    const hasAside = aside !== undefined;
    const cardMaxWidth = hasAside
        ? 'max-w-5xl'
        : wide
          ? 'max-w-xl'
          : 'max-w-md';

    const currentIndex = step === null ? -1 : STEPS.indexOf(step);

    return (
        <>
            <Head title={t('common.auth.login.page_title')} />

            <div className="flex min-h-screen flex-col bg-souk-paper px-5 py-6 sm:px-8">
                {/* ── Top bar ─────────────────────────────────────────── */}
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

                {/* ── Card ────────────────────────────────────────────── */}
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

                    <div
                        className={`mt-6 grid w-full items-center gap-10 ${cardMaxWidth} ${
                            hasAside
                                ? 'lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1fr)]'
                                : ''
                        }`}
                    >
                        {/* Decoration, so it is the first thing dropped on a
                            narrow screen rather than pushing the form below
                            the fold. */}
                        {hasAside && (
                            <div className="hidden lg:block">{aside}</div>
                        )}

                        <div className="w-full rounded-2xl p-6 shadow-sm shadow-souk-brown/5 sm:p-8">
                            {/* Step indicator — signup branch only, hence the
                            `step !== null` guard. An ordered list, so a screen
                            reader reads it as the sequence it is, and the
                            current step is marked with aria-current rather
                            than colour alone. */}
                            {step !== null && (
                                <>
                                    <ol className="flex items-center gap-2">
                                        {STEPS.map((s, i) => {
                                            const done = i < currentIndex;
                                            const active = i === currentIndex;

                                            return (
                                                <li
                                                    key={s}
                                                    aria-current={
                                                        active
                                                            ? 'step'
                                                            : undefined
                                                    }
                                                    className="flex flex-1 items-center gap-2"
                                                >
                                                    <span
                                                        className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] transition-colors ${
                                                            done
                                                                ? 'bg-souk-brown text-souk-cream'
                                                                : active
                                                                  ? 'border border-souk-brown bg-souk-brown/10 text-souk-brown'
                                                                  : 'border border-souk-brown/20 text-souk-brown-deep/35'
                                                        }`}
                                                    >
                                                        {done ? (
                                                            <Check
                                                                className="h-3 w-3"
                                                                strokeWidth={
                                                                    2.5
                                                                }
                                                            />
                                                        ) : (
                                                            i + 1
                                                        )}
                                                    </span>
                                                    <span
                                                        className={`truncate text-[11px] tracking-normal ${
                                                            active
                                                                ? 'text-souk-title'
                                                                : 'text-souk-brown-deep/40'
                                                        }`}
                                                    >
                                                        {t(
                                                            `common.auth.login.step_${s}`,
                                                        )}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ol>

                                    <p className="mt-6 text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                                        {t('common.auth.login.step_of')
                                            .replace(
                                                ':current',
                                                String(currentIndex + 1),
                                            )
                                            .replace(
                                                ':total',
                                                String(STEPS.length),
                                            )}
                                    </p>
                                </>
                            )}

                            <h1 className="mt-6 font-display text-[clamp(1.5rem,3.2vw,2rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                                {title}{' '}
                                <span className="text-souk-brown">
                                    {titleAccent}
                                </span>
                            </h1>

                            <p className="mt-2.5 text-sm leading-relaxed text-souk-brown-deep/70">
                                {subtitle}
                            </p>

                            {children}
                        </div>
                    </div>

                    {footer && (
                        <div className={`mt-6 w-full ${cardMaxWidth}`}>
                            {footer}
                        </div>
                    )}

                    {/* ── Little pictures ─────────────────────────────── */}
                    {/* Suppressed when an aside is supplied (step 1 already
                        carries the photo deck) or when the page opts out via
                        `thumbnails={false}` — profile completion is dense
                        enough with fields that it does not need a decorative
                        strip pulling the eye further down. */}
                    {!hasAside && thumbnails && (
                        <div
                            aria-hidden="true"
                            className="mt-8 flex items-center justify-center gap-2.5"
                        >
                            {THUMBNAILS.map((src, i) => (
                                <img
                                    key={i}
                                    src={src}
                                    alt=""
                                    loading="lazy"
                                    className="h-12 w-12 rounded-lg object-cover ring-1 ring-souk-brown/10 sm:h-14 sm:w-14"
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
