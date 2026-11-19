import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRight,
    CheckCircle2,
    Layers,
    Zap,
} from 'lucide-react';
import { useRef } from 'react';
import ArtisanCta from '@/components/ArtisanCta';
import {
    CratesIcon,
    StorefrontIcon,
    WholesaleTagIcon,
    ZellijStarIcon,
} from '@/components/home/CraftIcons';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { switchPriceMode, usePriceMode } from '@/hooks/use-price-mode';
import { useTranslation } from '@/hooks/use-translation';
import pottery from '@/images/cat-pottery.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';
import heroWorkshop from '@/images/hero/hero-3.jpg';
import workshop from '@/images/hero-main.jpg';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.22em] uppercase';

/** Section heading, as the home page and /about set theirs. */
const HEADING =
    'font-display text-[clamp(1.9rem,3.6vw,2.9rem)] leading-[1.06] font-bold tracking-[-0.02em] text-souk-title';

/** Figures for the illustration only — labelled as such on the page. */
const DEMO = { public: 480, trade: 336, minQty: 12 } as const;
const DEMO_SAVING = Math.round((1 - DEMO.trade / DEMO.public) * 100);

/** The same hand-drawn icons the home page's wholesale branch uses. */
const PERKS = [
    { key: 'pricing', Icon: WholesaleTagIcon },
    { key: 'catalogue', Icon: CratesIcon },
    { key: 'direct', Icon: StorefrontIcon },
] as const;

/** One photograph per step, so the sequence reads visually as well as in prose. */
const STEPS = [
    { n: 1, src: pottery },
    { n: 2, src: textile },
    { n: 3, src: wood },
] as const;

const NOTES = [
    { key: 'open', Icon: Zap },
    { key: 'minimum', Icon: Layers },
    { key: 'switch', Icon: ArrowLeftRight },
] as const;

/**
 * The thin rule with a lozenge at its centre — the divider the home page
 * draws between its sections' parts.
 */
function Rule({ light = false }: { light?: boolean }) {
    return (
        <span
            aria-hidden="true"
            className="mx-auto flex w-24 items-center gap-2"
        >
            <span
                className={`h-px flex-1 ${light ? 'bg-souk-cream/40' : 'bg-souk-brown/25'}`}
            />
            <span
                className={`h-1.5 w-1.5 shrink-0 rotate-45 border ${light ? 'border-souk-cream/60' : 'border-souk-brown/45'}`}
            />
            <span
                className={`h-px flex-1 ${light ? 'bg-souk-cream/40' : 'bg-souk-brown/25'}`}
            />
        </span>
    );
}

/** Centred eyebrow + heading, the way every home section opens. */
function SectionTitle({
    eyebrow,
    heading,
    lead,
}: {
    eyebrow: string;
    heading: string;
    lead?: string;
}) {
    return (
        <div className="mx-auto max-w-2xl text-center">
            <Reveal>
                <p className={`text-souk-brown/80 ${EYEBROW}`}>{eyebrow}</p>
            </Reveal>
            <Reveal delay={80}>
                <h2 className={`mt-4 ${HEADING}`}>{heading}</h2>
            </Reveal>
            {lead && (
                <Reveal delay={140}>
                    <p className="mt-5 text-lg leading-relaxed text-souk-brown-deep/75">
                        {lead}
                    </p>
                </Reveal>
            )}
            <Reveal delay={180}>
                <div className="mt-7">
                    <Rule />
                </div>
            </Reveal>
        </div>
    );
}

/**
 * One tile of the price illustration, framed like a catalogue card
 * (CollectionCard) so the reader recognises what they will see in the shop.
 *
 * Both sides render the same photograph and the same piece — that *is* the
 * point being made, so drawing them from one component rather than two keeps
 * them honestly identical apart from the price block.
 */
function DemoCard({
    label,
    trade,
    t,
    money,
}: {
    label: string;
    trade: boolean;
    t: (k: string) => string;
    money: (v: number) => string;
}) {
    return (
        <div>
            <p
                className={`mb-3 text-center ${EYEBROW} ${trade ? 'text-souk-brown' : 'text-souk-brown-deep/55'}`}
            >
                {label}
            </p>

            <div
                className={`rounded-md border bg-souk-paper p-1.5 transition-shadow duration-500 sm:p-2 ${
                    trade
                        ? 'border-souk-brown shadow-[0_24px_50px_-30px_var(--color-souk-brown)]'
                        : 'border-souk-brown/25'
                }`}
            >
                <div className="relative aspect-[4/3] overflow-hidden border border-souk-brown/20 bg-white">
                    <img
                        src={pottery}
                        alt=""
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                    {trade && (
                        <span className="absolute start-3 top-3 rounded-full bg-souk-brown px-3 py-1 text-[10px] font-medium tracking-[0.16em] text-souk-cream uppercase">
                            {t('pro.demo.badge')}
                        </span>
                    )}
                </div>

                <div className="px-2.5 pt-4 pb-3 sm:px-3">
                    <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                        {t('pro.demo.workshop')}
                    </p>
                    <h3 className="mt-1.5 font-display text-lg leading-snug text-souk-title">
                        {t('pro.demo.product_name')}
                    </h3>

                    {/* Fixed height so the two cards stay the same size even
                        though only one of them carries a badge row. */}
                    <div className="mt-3 min-h-[5.25rem]">
                        {trade ? (
                            <>
                                {/*
                                 * The public figure is ruled through rather
                                 * than simply replaced: the line draws across
                                 * it and the trade price lifts in behind, so
                                 * the eye follows the substitution happening.
                                 * `origin-left` flips under RTL, or the rule
                                 * would draw away from the text instead of
                                 * across it.
                                 */}
                                <span className="relative inline-block text-sm text-souk-brown-deep/45">
                                    {money(DEMO.public)} {t('common.ttc')}
                                    <span
                                        aria-hidden="true"
                                        className="absolute inset-x-0 top-1/2 h-px origin-left bg-souk-brown-deep/45 motion-safe:animate-rule-through rtl:origin-right"
                                    />
                                </span>

                                <div className="motion-safe:animate-lift-in">
                                    <p className="mt-1 font-display text-3xl leading-none text-souk-brown">
                                        {money(DEMO.trade)}
                                        <span className="ms-1.5 text-sm">
                                            {t('common.ttc')}
                                        </span>
                                    </p>
                                    <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                        <span className="rounded-full bg-souk-brown/10 px-2 py-0.5 text-[11px] font-medium text-souk-brown">
                                            {t('pro.demo.saving').replace(
                                                ':percent',
                                                String(DEMO_SAVING),
                                            )}
                                        </span>
                                        <span className="text-[11px] text-souk-brown-deep/65">
                                            {t('pro.demo.min_note').replace(
                                                ':count',
                                                String(DEMO.minQty),
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </>
                        ) : (
                            <p className="pt-5 font-display text-3xl leading-none text-souk-title">
                                {money(DEMO.public)}
                                <span className="ms-1.5 text-sm">
                                    {t('common.ttc')}
                                </span>
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * How pro prices work — for the visitor deciding whether to switch.
 *
 * There is nothing to apply for: anyone shops on the pro list by choosing
 * "Pro" (PriceModeService), and each piece is then sold from the minimum
 * quantity the admin set on it. So the page explains that and offers the
 * switch, straight into the catalogue.
 *
 * Laid out the way /about and the home page are: a full-bleed photograph
 * under the overlay header, then light paper and peach sections with centred
 * headings, arch-topped photography and a single brown band for the action.
 */
export default function ProfessionalAccount() {
    const { t, locale } = useTranslation();
    const { isPro } = usePriceMode();

    /* The overlay bar stays transparent until the hero has scrolled away. */
    const heroRef = useRef<HTMLElement>(null);

    const intlLocale =
        { fr: 'fr-MA', ar: 'ar-MA', en: 'en-MA' }[locale as string] ?? 'fr-MA';

    const money = (v: number) =>
        new Intl.NumberFormat(intlLocale, {
            maximumFractionDigits: 0,
        }).format(v);

    /** Straight to the catalogue at pro prices — the page's best argument. */
    const seeProPrices = () =>
        switchPriceMode('professional', {
            onSuccess: () => router.visit(route('products.index')),
        });

    /**
     * The page's one action, twice (hero and closing band): switch to pro and
     * go shopping — or, already in pro mode, just go shopping.
     */
    const action = (tone: 'light' | 'dark') => {
        const classes =
            tone === 'light'
                ? 'bg-souk-cream text-souk-brown-deep shadow-lg shadow-black/20 hover:bg-white'
                : 'bg-souk-brown text-souk-cream hover:bg-souk-brown-deep';
        const inner = (
            <>
                {isPro ? t('pro.hero.browse') : t('pro.hero.cta')}
                <ArrowRight
                    className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                    strokeWidth={1.75}
                />
            </>
        );

        return isPro ? (
            <Link
                href={route('products.index')}
                className={`group inline-flex items-center gap-3 rounded-sm px-8 py-4 font-display text-lg transition-colors duration-300 ${classes}`}
            >
                {inner}
            </Link>
        ) : (
            <button
                type="button"
                onClick={seeProPrices}
                className={`group inline-flex cursor-pointer items-center gap-3 rounded-sm px-8 py-4 font-display text-lg transition-colors duration-300 ${classes}`}
            >
                {inner}
            </button>
        );
    };

    return (
        <>
            <Head title={t('pro.meta.title')} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader variant="overlay" overlayAnchor={heroRef} />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <section
                    ref={heroRef}
                    className="relative isolate overflow-hidden"
                >
                    <img
                        src={heroWorkshop}
                        alt=""
                        loading="eager"
                        className="absolute inset-0 -z-10 h-full w-full object-cover motion-safe:animate-ken-burns"
                    />
                    {/* Darkens the photograph so the type reads over it. */}
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-10 bg-gradient-to-b from-souk-title/75 via-souk-title/60 to-souk-title/80"
                    />

                    <div className="container flex min-h-[82svh] flex-col items-center justify-center pt-32 pb-20 text-center lg:pt-36 lg:pb-24">
                        <Reveal>
                            <p
                                className={`flex items-center justify-center gap-2.5 text-souk-cream/85 ${EYEBROW}`}
                            >
                                <ZellijStarIcon className="h-3.5 w-3.5" />
                                {t('pro.hero.eyebrow')}
                            </p>
                        </Reveal>

                        <h1 className="mt-6 max-w-4xl font-display text-[clamp(2.3rem,5.4vw,4.4rem)] leading-[1.02] font-bold tracking-[-0.02em] text-white">
                            <Reveal delay={80}>
                                <span className="block">
                                    {t('pro.hero.title1')}
                                </span>
                            </Reveal>
                            <Reveal delay={170}>
                                <span className="block text-souk-cream">
                                    {t('pro.hero.title2')}
                                </span>
                            </Reveal>
                        </h1>

                        <Reveal delay={260}>
                            <p className="mx-auto mt-7 max-w-2xl text-lg leading-relaxed text-white/85 sm:text-xl">
                                {t('pro.hero.lead')}
                            </p>
                        </Reveal>

                        <Reveal delay={340}>
                            <div className="mt-10 flex flex-wrap items-center justify-center gap-3.5">
                                {/* Already in pro mode: say so, then offer the
                                    catalogue rather than a switch to nowhere. */}
                                {isPro && (
                                    <p className="inline-flex items-center gap-2.5 rounded-sm border border-souk-cream/40 bg-souk-title/30 px-6 py-3.5 text-souk-cream backdrop-blur-sm">
                                        <CheckCircle2
                                            className="h-5 w-5 shrink-0"
                                            strokeWidth={1.6}
                                        />
                                        {t('pro.hero.cta_active')}
                                    </p>
                                )}
                                {action('light')}
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── What you get ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-paper py-16 lg:py-24">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-tadelakt motif-fade [--pattern-size:38px]"
                    />

                    <div className="relative container">
                        <SectionTitle
                            eyebrow={t('pro.perks.eyebrow')}
                            heading={t('pro.perks.heading')}
                        />

                        <div className="mt-14 grid gap-12 md:grid-cols-3 md:gap-0 md:divide-x md:divide-souk-brown/12">
                            {PERKS.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 120}>
                                    <article className="group mx-auto max-w-sm px-2 text-center md:px-8 lg:px-10">
                                        <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-t-full rounded-b-lg bg-souk-peach text-souk-brown ring-1 ring-souk-brown/15 transition-colors duration-500 group-hover:bg-souk-brown group-hover:text-souk-cream">
                                            <Icon className="h-8 w-8" />
                                        </span>
                                        <h3 className="mt-6 font-display text-xl leading-snug text-souk-title">
                                            {t(`pro.perks.${key}.title`)}
                                        </h3>
                                        <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`pro.perks.${key}.body`)}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────── The price illustration ───────────────── */}
                <section className="border-y border-souk-brown/10 bg-souk-peach py-16 lg:py-24">
                    <div className="container">
                        <SectionTitle
                            eyebrow={t('pro.demo.eyebrow')}
                            heading={t('pro.demo.heading')}
                            lead={t('pro.demo.lead')}
                        />

                        <div className="mx-auto mt-12 grid max-w-3xl gap-6 sm:grid-cols-2 sm:gap-8">
                            <Reveal from="left">
                                <DemoCard
                                    label={t('pro.demo.public_label')}
                                    trade={false}
                                    t={t}
                                    money={money}
                                />
                            </Reveal>
                            <Reveal from="right" delay={120}>
                                <DemoCard
                                    label={t('pro.demo.trade_label')}
                                    trade
                                    t={t}
                                    money={money}
                                />
                            </Reveal>
                        </div>

                        <Reveal delay={220}>
                            <p className="mt-7 text-center text-xs text-souk-brown-deep/55">
                                {t('pro.demo.disclaimer')}
                            </p>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── How it works ───────────────────── */}
                <section className="bg-souk-paper py-16 lg:py-24">
                    <div className="container">
                        <SectionTitle
                            eyebrow={t('pro.steps.eyebrow')}
                            heading={t('pro.steps.heading')}
                        />

                        <ol className="relative mt-14 grid gap-14 md:grid-cols-3 md:gap-8">
                            {/* The thread the three doorways hang from. */}
                            <span
                                aria-hidden="true"
                                className="absolute inset-x-[16%] top-[8.5rem] hidden h-px bg-souk-brown/20 md:block"
                            />

                            {STEPS.map(({ n, src }, i) => (
                                <Reveal key={n} delay={i * 130} from="scale">
                                    <li className="group relative flex flex-col items-center text-center">
                                        {/* Arch-topped, as everywhere else
                                            on the site — a workshop doorway
                                            rather than a rounded rectangle. */}
                                        <div className="relative h-[17rem] w-[12.5rem] overflow-hidden rounded-t-full rounded-b-lg bg-souk-brown/5 ring-1 ring-souk-brown/15">
                                            <img
                                                src={src}
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover transition-transform duration-[900ms] group-hover:scale-105"
                                            />
                                            <div
                                                aria-hidden="true"
                                                className="absolute inset-0 bg-souk-title/10"
                                            />
                                        </div>
                                        <span className="relative z-10 -mt-6 flex h-12 w-12 items-center justify-center rounded-full border border-souk-brown/20 bg-souk-paper font-display text-xl text-souk-brown tabular-nums shadow-md shadow-souk-brown/10">
                                            {n}
                                        </span>
                                        <h3 className="mt-5 font-display text-xl leading-snug text-souk-title">
                                            {t(`pro.steps.${n}.title`)}
                                        </h3>
                                        <p className="mt-3 max-w-xs text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`pro.steps.${n}.body`)}
                                        </p>
                                    </li>
                                </Reveal>
                            ))}
                        </ol>
                    </div>
                </section>

                {/* ────────────────────── Good to know ────────────────────── */}
                <section className="border-y border-souk-brown/10 bg-souk-peach py-16 lg:py-24">
                    <div className="container grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                        <div className="text-center lg:sticky lg:top-28 lg:self-start lg:text-start">
                            <Reveal>
                                <p className={`text-souk-brown/80 ${EYEBROW}`}>
                                    {t('pro.notes.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className={`mt-4 ${HEADING}`}>
                                    {t('pro.notes.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <div className="divide-y divide-souk-brown/12 border-y border-souk-brown/12">
                            {NOTES.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 100}>
                                    <div className="flex gap-5 py-7">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-t-full rounded-b-md bg-souk-paper text-souk-brown ring-1 ring-souk-brown/15">
                                            <Icon
                                                className="h-5 w-5"
                                                strokeWidth={1.5}
                                            />
                                        </span>
                                        <div>
                                            <h3 className="font-display text-lg leading-snug text-souk-title">
                                                {t(`pro.notes.${key}.title`)}
                                            </h3>
                                            <p className="mt-2 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t(`pro.notes.${key}.body`)}
                                            </p>
                                        </div>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ─────────────────────── The way in ─────────────────────── */}
                <section className="bg-souk-paper py-16 lg:py-24">
                    <div className="container">
                        <Reveal from="scale">
                            {/*
                             * The page's one brown band, built like the home
                             * page's brochure banner: the photograph fills
                             * the leading half on large screens, the steps
                             * and the switch sit on a paper card on the other.
                             */}
                            <div className="relative isolate grid overflow-hidden rounded-md bg-souk-brown lg:grid-cols-2">
                                <div className="relative flex flex-col justify-end px-6 pt-10 pb-8 sm:px-10 lg:min-h-[30rem] lg:py-14">
                                    <img
                                        src={workshop}
                                        alt=""
                                        loading="lazy"
                                        className="absolute inset-0 -z-10 hidden h-full w-full object-cover lg:block"
                                    />
                                    <div
                                        aria-hidden="true"
                                        className="absolute inset-0 -z-10 hidden bg-gradient-to-t from-souk-brown via-souk-brown/70 to-souk-brown/10 lg:block"
                                    />

                                    <ZellijStarIcon className="h-7 w-7 text-souk-cream/70" />
                                    <h2 className="mt-5 font-display text-[clamp(1.7rem,3vw,2.4rem)] leading-[1.1] font-bold text-white">
                                        {isPro
                                            ? t('pro.cta.active_heading')
                                            : t('pro.cta.heading')}
                                    </h2>
                                    <p className="mt-4 max-w-md text-[15px] leading-relaxed text-souk-cream/85">
                                        {isPro
                                            ? t('pro.cta.active_body')
                                            : t('pro.cta.body')}
                                    </p>
                                </div>

                                <div className="p-2 sm:p-3">
                                    <div className="flex h-full flex-col justify-center rounded-sm bg-souk-paper px-6 py-8 sm:px-10 sm:py-12">
                                        <ol className="space-y-5">
                                            {STEPS.map(({ n }) => (
                                                <li
                                                    key={n}
                                                    className="flex items-start gap-4"
                                                >
                                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-souk-brown/10 font-display text-souk-brown">
                                                        {n}
                                                    </span>
                                                    <span className="pt-1 font-display text-[17px] leading-snug text-souk-title">
                                                        {t(
                                                            `pro.steps.${n}.title`,
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ol>

                                        <div className="mt-8">
                                            {action('dark')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Reveal>
                    </div>
                </section>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
