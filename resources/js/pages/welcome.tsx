import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ArtisanCta from '@/components/ArtisanCta';
import {
    AtelierArchIcon,
    CratesIcon,
    ParcelIcon,
    PriceTagIcon,
    RepeatOrderIcon,
    StorefrontIcon,
    WholesaleTagIcon,
    ZellijStarIcon,
} from '@/components/home/CraftIcons';
import HeroBackdrop from '@/components/home/HeroBackdrop';
import MoroccoMap, {
    MOROCCO_ASPECT,
    projectMorocco,
} from '@/components/home/MoroccoMap';
import type { RailProduct } from '@/components/home/ProductRail';
import ProductRail from '@/components/home/ProductRail';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import accessories from '@/images/cat-accessories.jpg';
import jewelry from '@/images/cat-jewelry.jpg';
import leather from '@/images/cat-leather.jpg';
import pottery from '@/images/cat-pottery.jpg';
import spice from '@/images/cat-spice.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';
import heroMain from '@/images/hero-main.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';

//change

/** Fallback tiles for the product strip before any product is published. */
const SHOWCASE = [pottery, leather, wood, textile, jewelry, spice];

/**
 * The hero's crossfading backdrop. Three rather than more: each is a full
 * viewport of photography, and a fourth would be downloaded by everyone but
 * seen by almost nobody — the section is usually scrolled past inside two
 * rotations. Alt text is a translation key resolved at render, so the
 * descriptions follow the active locale like the rest of the page.
 */
const HERO_SLIDES = [
    { src: heroMain, altKey: 'welcome.hero.alt' },
    { src: heroTextile, altKey: 'welcome.hero.alt_textile' },
    { src: heroZellige, altKey: 'welcome.hero.alt_zellige' },
] as const;

/*
 * The hero's proof line. Figures are hardcoded rather than counted from the
 * database on purpose — they are a marketing claim about the marketplace as a
 * whole, not a live count, and the home page should not pay for four
 * aggregate queries to render them. Only the labels are translated.
 *
 * Commented out alongside the <dl> that consumed it, further down in the
 * hero — left in place rather than deleted so the pair can be restored
 * together. The `welcome.hero.stat_*` keys are still defined in all three
 * locales.
 */
// const HERO_STATS = [
//     ['200+', 'stat_artisans'],
//     ['1,400+', 'stat_products'],
//     ['18', 'stat_crafts'],
//     ['50+', 'stat_cities'],
// ] as const;

/**
 * The three things a visitor can actually do here, as content rather than
 * markup — the cards are structurally identical, so describing them as data
 * keeps the single card template below honest about that.
 *
 * `key` is both the React key and the translation namespace
 * (`welcome.pillars.<key>.*`), so a card's copy is found in one place.
 */
const PILLARS = [
    {
        key: 'retail',
        href: '/products',
        Icon: StorefrontIcon,
        image: pottery,
    },
    {
        key: 'wholesale',
        href: '/professional-account',
        Icon: WholesaleTagIcon,
        image: spice,
    },
    {
        key: 'seller',
        href: '/seller/onboarding',
        Icon: AtelierArchIcon,
        image: leather,
    },
] as const;

/**
 * The cities pinned on the map, as real coordinates.
 *
 * Kept as longitude/latitude rather than as pixels so they stay meaningful:
 * `projectMorocco` turns them into positions on the artwork, so the pins
 * cannot drift out of register with the coastline the way hand-placed
 * pixel offsets would.
 *
 * Mind the order of the pair. Every Moroccan city is west of Greenwich and
 * north of the equator, so `lon` is always negative and `lat` always
 * positive — a swapped pair projects clean off the canvas rather than
 * failing loudly, and the pin simply vanishes.
 *
 * `key` is the translation suffix (`welcome.map.city.<key>`) — the names are
 * transliterated differently per locale (Tangier / Tanger / طنجة), so they
 * are content, not constants.
 *
 * `labelAbove` lifts a name over its pin instead of hanging it underneath.
 * Only Meknes needs it: it sits ~50 km from Fes, so at the size this draws
 * the two pins are a few pixels apart and their labels, both centred under
 * their own pin, overlapped into an unreadable smear. Separating them
 * vertically is what makes both legible — nudging them sideways would only
 * have moved the collision.
 */
const MAP_CITIES = [
    { key: 'tanger', lat: 35.7595, lon: -5.7998, labelAbove: false },
    { key: 'fes', lat: 34.0331, lon: -5.0003, labelAbove: false },
    { key: 'meknes', lat: 33.8935, lon: -5.5473, labelAbove: true },
    { key: 'safi', lat: 32.2994, lon: -9.2372, labelAbove: false },
    { key: 'marrakech', lat: 31.6295, lon: -7.9811, labelAbove: false },
    { key: 'agadir', lat: 30.4278, lon: -9.5981, labelAbove: false },
    { key: 'laayoune', lat: 27.1536, lon: -13.2033, labelAbove: false },
] as const;

/**
 * The drifting gallery strip.
 *
 * Heights and vertical offsets vary per tile on purpose: an even row of
 * identical cards reads as a filmstrip, whereas the staggered arrangement
 * reads as pieces hung on a wall. `pottery` is the tallest and sits level,
 * so the eye has one fixed point to settle on as the rest drifts past.
 *
 * Rendered twice at half speed each pass — see the `animate-gallery` note in
 * app.css and the duplication in the markup below.
 */
const GALLERY = [
    {
        src: jewelry,
        alt: 'Silver Moroccan jewellery',
        box: 'h-64 md:h-72',
        shift: 'translate-y-6',
    },
    {
        src: wood,
        alt: 'Carved cedar wood detail',
        box: 'h-72 md:h-88',
        shift: '-translate-y-4',
    },
    {
        src: pottery,
        alt: 'A potter at the wheel',
        box: 'h-80 md:h-96',
        shift: 'translate-y-0',
    },
    {
        src: textile,
        alt: 'Handwoven Moroccan textile',
        box: 'h-64 md:h-80',
        shift: 'translate-y-8',
    },
    {
        src: accessories,
        alt: 'Handmade artisan accessories',
        box: 'h-72 md:h-88',
        shift: '-translate-y-2',
    },
    {
        src: spice,
        alt: 'Moroccan spices at the souk',
        box: 'h-64 md:h-72',
        shift: 'translate-y-5',
    },
] as const;

/**
 * The two selling models, as content rather than markup — the branches are
 * mirror images of each other, so describing them as data keeps the single
 * `AudienceBranch` below honest about that. `chips` are the abstract traces
 * of each model: retail shows individual pieces with a price, wholesale
 * shows the same piece by the crate.
 */
const AUDIENCE = {
    retail: {
        key: 'b2c',
        features: [StorefrontIcon, PriceTagIcon, ParcelIcon],
        thumbs: [leather, textile],
    },
    wholesale: {
        key: 'b2b',
        features: [WholesaleTagIcon, CratesIcon, RepeatOrderIcon],
        thumbs: [wood, spice],
    },
} as const;

type AudienceModel = (typeof AUDIENCE)[keyof typeof AUDIENCE];

/**
 * One arm of the composition.
 *
 * `side` is the edge it hangs off — `start` for the retail branch, `end` for
 * wholesale — expressed logically rather than as left/right so the whole
 * thing mirrors under `dir="rtl"` without a second set of classes. It drives
 * three things at `lg`: which way the connector runs back to the workshop,
 * which way the text aligns, and which direction the content travels in from
 * as it reveals.
 */
function AudienceBranch({
    side,
    model,
    t,
}: {
    side: 'start' | 'end';
    model: AudienceModel;
    t: (key: string) => string;
}) {
    const isStart = side === 'start';
    const { key, features, thumbs } = model;

    return (
        /*
         * Centred until the three tracks actually form, then pushed against
         * the workshop in the middle.
         *
         * Below `lg` the branches stack under the workshop rather than
         * flanking it, so hanging them off an edge left them adrift against a
         * centred heading and a centred workshop — most visible on a tablet,
         * which is wide enough to show the misalignment but too narrow for the
         * three-column layout that would justify it. `lg:text-start` has to be
         * spelled out on the trailing branch because `text-center` here would
         * otherwise cascade past it.
         */
        <div
            className={`flex flex-col items-center text-center ${isStart ? 'lg:items-end lg:text-end' : 'lg:items-start lg:text-start'}`}
        >
            {/*
             * The connector, drawn only once the columns sit side by side —
             * stacked, there is nothing horizontal to connect. It slides out
             * of the workshop rather than fading in place: `overflow-hidden`
             * plus a reveal travelling from the workshop's side makes the
             * rule read as being drawn outward from it.
             */}
            <div
                aria-hidden="true"
                className={`hidden lg:mb-10 lg:block lg:w-full lg:overflow-hidden ${isStart ? 'lg:ps-6 lg:pe-0' : 'lg:ps-0 lg:pe-6'}`}
            >
                <Reveal from={isStart ? 'right' : 'left'} delay={260}>
                    <span className="flex items-center gap-2">
                        {isStart && (
                            <span className="h-1.5 w-1.5 shrink-0 rotate-45 border border-souk-brown/40" />
                        )}
                        <span className="h-px flex-1 bg-souk-brown/25" />
                        {!isStart && (
                            <span className="h-1.5 w-1.5 shrink-0 rotate-45 border border-souk-brown/40" />
                        )}
                    </span>
                </Reveal>
            </div>

            <Reveal from={isStart ? 'right' : 'left'} delay={300}>
                <p className="text-[11px] tracking-[0.24em] text-souk-brown/70 uppercase">
                    {t(`welcome.audience.${key}.tag`)}
                </p>

                <h3 className="mt-4 font-display text-[clamp(1.6rem,2.6vw,2.25rem)] leading-tight text-souk-title">
                    {t(`welcome.audience.${key}.title`)}
                </h3>

                {/* `mx-auto` so the measure stays centred under the heading
                    while the branch is stacked, rather than hugging one edge
                    of a box that is itself centred. */}
                <p className="mx-auto mt-4 max-w-sm leading-relaxed text-souk-brown-deep/75 lg:mx-0">
                    {t(`welcome.audience.${key}.body`)}
                </p>

                {/*
                 * The abstract trace of the model — the same two photographs
                 * read two different ways: retail prices a single piece,
                 * wholesale carries a quantity over the pair.
                 */}
                <div
                    className={`mt-8 flex items-end justify-center gap-3 ${isStart ? 'lg:justify-end' : 'lg:justify-start'}`}
                >
                    {thumbs.map((src) => (
                        <span
                            key={src}
                            className="relative block h-32 w-28 overflow-hidden rounded-lg ring-1 ring-souk-brown/15"
                        >
                            <img
                                src={src}
                                alt=""
                                loading="lazy"
                                className="h-full w-full object-cover"
                            />
                        </span>
                    ))}

                    <span className="ms-1 self-center border border-souk-brown/20 px-2.5 py-1 font-display text-sm text-souk-brown-deep">
                        {t(`welcome.audience.${key}.chip`)}
                    </span>
                </div>

                <ul className="mt-9 space-y-4 border-t border-souk-brown/12 pt-7">
                    {features.map((Icon, i) => (
                        <li
                            key={i}
                            className={`flex items-center justify-center gap-3.5 text-sm text-souk-brown-deep/85 lg:justify-start ${isStart ? 'lg:flex-row-reverse' : ''}`}
                        >
                            <Icon className="h-6 w-6 shrink-0 text-souk-brown" />
                            {t(`welcome.audience.${key}.point${i + 1}`)}
                        </li>
                    ))}
                </ul>
            </Reveal>
        </div>
    );
}

export default function Welcome({
    featuredProducts = [],
}: {
    canRegister?: boolean;
    featuredProducts?: RailProduct[];
}) {
    /*
     * `isRTL` drives two things. `Reveal`'s `from` is a physical translate
     * (`translate-x-*` has no logical form), so a two-column block that swaps
     * sides under `dir="rtl"` — the map section — has to swap its travel
     * directions with it. The product rail needs it for a different reason:
     * `scrollLeft` runs negative in a right-to-left container, so its arrows
     * page the other way.
     */
    const { t, isRTL } = useTranslation();

    return (
        <>
            <Head title="Hirfaty — Moroccan craft, direct from the artisans" />

            <div className="min-h-screen bg-souk-paper">
                {/* Pinned and transparent until the page scrolls, so the hero
                    photograph runs the full height of the viewport behind it. */}
                <SiteHeader variant="overlay" />

                {/* ───────────────────────── Hero ─────────────────────────
                    Three full-bleed photographs crossfading behind the claim,
                    rather than one still: the page opens on the range of the
                    catalogue instead of a single craft. The stack carries its
                    own scrim — see HeroBackdrop. */}
                <section className="relative overflow-hidden">
                    <HeroBackdrop
                        slides={HERO_SLIDES.map((slide) => ({
                            src: slide.src,
                            alt: t(slide.altKey),
                        }))}
                        label={t('welcome.hero.slides_label')}
                    />

                    {/*
                     * `lg:min-h-svh` rather than a flat `h-screen`: this is a
                     * *floor* tied to the actual viewport, with `items-center`
                     * doing the vertical centring, so a laptop gets a hero
                     * sized to fit without scrolling while a longer
                     * translation (Arabic runs bulkier) simply grows the
                     * section instead of clipping.
                     *
                     * A full viewport rather than one minus the header,
                     * because the header is now `overlay` — pinned on top of
                     * this section rather than stacked above it. The extra top
                     * padding is what keeps the copy clear of the bar; 5.75rem
                     * is SiteHeader's own rendered height.
                     */}
                    <div className="relative container flex items-center py-20 pt-32 lg:min-h-svh lg:py-24 lg:pt-36">
                        <div className="max-w-[42.5rem]">
                            <h1 className="mt-6 font-display text-[clamp(2.25rem,6vw,4.5rem)] leading-[1.1] font-bold tracking-[-0.02em] text-white">
                                <Reveal delay={80}>
                                    <span className="block">
                                        {t('welcome.hero.line1')}
                                    </span>
                                </Reveal>
                                <Reveal delay={180}>
                                    <span className="block text-souk-cream italic">
                                        {t('welcome.hero.line2')}
                                    </span>
                                </Reveal>
                            </h1>

                            <Reveal delay={280}>
                                <p className="mt-6 max-w-lg text-lg leading-relaxed text-souk-cream/85">
                                    {t('welcome.hero.lead')}
                                </p>
                            </Reveal>

                            <Reveal delay={360}>
                                <div className="mt-9 flex flex-wrap items-center gap-3">
                                    <Link
                                        href="/products"
                                        className="group relative inline-flex items-center gap-2.5 overflow-hidden rounded-xl bg-souk-brown px-7 py-3.5 font-semibold text-souk-cream shadow-lg shadow-black/25 transition-transform duration-300 hover:-translate-y-0.5"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="absolute inset-y-0 -left-1/3 w-1/3 bg-white/30 opacity-0 group-hover:animate-sheen group-hover:opacity-100"
                                        />
                                        <StorefrontIcon className="h-5 w-5 shrink-0" />
                                        {t('welcome.hero.cta_primary')}
                                    </Link>
                                    <Link
                                        href="/seller/onboarding"
                                        className="inline-flex items-center gap-2.5 rounded-xl border-[1.5px] border-souk-cream/40 bg-souk-cream/15 px-7 py-3.5 font-semibold text-souk-cream backdrop-blur transition-colors duration-300 hover:bg-souk-cream/25"
                                    >
                                        <AtelierArchIcon className="h-5 w-5 shrink-0" />
                                        {t('welcome.hero.cta_secondary')}
                                    </Link>
                                </div>
                            </Reveal>

                            {/* <Reveal delay={440}>
                                <dl className="mt-13 flex flex-wrap gap-x-10 gap-y-6">
                                    {HERO_STATS.map(([value, key]) => (
                                        <div key={key}>
                                            <dt className="font-display text-3xl font-bold text-souk-cream">
                                                {value}
                                            </dt>
                                            <dd className="mt-1 text-xs tracking-[0.08em] text-white/60 uppercase">
                                                {t(`welcome.hero.${key}`)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </Reveal> */}
                        </div>
                    </div>
                </section>

                {/* ───────────────── Explore our products ─────────────────
                    Directly under the hero: the first thing a visitor should
                    see is what is actually for sale, not an explanation of
                    the marketplace. A rail rather than a grid so a full row
                    of stock is reachable without the section swallowing a
                    screen and a half. */}
                <section className="relative overflow-hidden bg-souk-paper py-24 lg:py-32">
                    <div className="relative container">
                        <div className="flex flex-wrap items-end justify-between gap-6">
                            <div className="max-w-2xl">
                                <Reveal delay={80}>
                                    <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                                        {t('welcome.products.heading')}
                                    </h2>
                                </Reveal>
                                <Reveal delay={140}>
                                    <p className="mt-5 text-lg text-souk-brown-deep/75">
                                        {t('welcome.products.lead')}
                                    </p>
                                </Reveal>
                            </div>
                            <Reveal delay={200}>
                                <Link
                                    href="/products"
                                    className="group inline-flex items-center gap-2 border-b border-souk-brown/40 pb-1 text-souk-brown transition-colors hover:border-souk-brown"
                                >
                                    {t('welcome.products.view_all')}
                                    <ArrowRight
                                        className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </Reveal>
                        </div>

                        {featuredProducts.length > 0 ? (
                            <div className="mt-14">
                                <ProductRail
                                    products={featuredProducts}
                                    isRTL={isRTL}
                                    currency={t('welcome.products.currency')}
                                    prevLabel={t('welcome.products.prev')}
                                    nextLabel={t('welcome.products.next')}
                                />
                            </div>
                        ) : (
                            <div className="mt-14">
                                <div className="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                                    {SHOWCASE.slice(0, 4).map((src, i) => (
                                        <Reveal key={src} delay={i * 100}>
                                            <div className="relative aspect-[4/5] overflow-hidden rounded-t-[8rem] rounded-b-2xl bg-souk-brown/5">
                                                <img
                                                    src={src}
                                                    alt=""
                                                    className="h-full w-full object-cover opacity-70"
                                                    loading="lazy"
                                                />
                                            </div>
                                        </Reveal>
                                    ))}
                                </div>
                                <p className="mt-12 text-center text-souk-brown-deep/70">
                                    {t('welcome.products.empty')}
                                </p>
                            </div>
                        )}
                    </div>
                </section>

                {/* ────────────────── Three ways in ──────────────────
                    The three things a visitor can actually do here, as
                    cards. Content lives in `PILLARS` above so the three stay
                    structurally identical — the only differences that matter
                    are the destination, the icon and the photograph. */}
                <section className="relative overflow-hidden bg-souk-paper py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-tadelakt motif-fade [--pattern-size:38px]"
                    />

                    <div className="relative container">
                        <div className="mx-auto max-w-3xl text-center">
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4.6vw,3.75rem)] leading-[1.06] font-bold tracking-[-0.02em] text-souk-title">
                                    <span className="block">
                                        {t('welcome.pillars.heading_line1')}
                                    </span>
                                    <span className="block text-souk-brown italic">
                                        {t('welcome.pillars.heading_line2')}
                                    </span>
                                </h2>
                            </Reveal>
                        </div>

                        <div className="mt-14 grid gap-7 sm:grid-cols-2 lg:mt-16 lg:grid-cols-3">
                            {PILLARS.map(({ key, href, Icon, image }, i) => (
                                <Reveal key={key} delay={i * 150}>
                                    <Link
                                        href={href}
                                        className="group flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-lg ring-1 shadow-souk-brown/10 ring-souk-brown/10 transition duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-souk-brown/20"
                                    >
                                        <div className="relative h-52 shrink-0 overflow-hidden">
                                            <img
                                                src={image}
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                            />
                                            <div
                                                aria-hidden="true"
                                                className="absolute inset-0 bg-gradient-to-t from-souk-title/85 via-souk-title/25 to-transparent"
                                            />

                                            <span className="absolute start-4 top-4 flex h-12 w-12 items-center justify-center rounded-xl border border-souk-cream/40 bg-souk-brown/70 text-souk-cream backdrop-blur">
                                                <Icon className="h-6 w-6" />
                                            </span>

                                            <div className="absolute start-4 end-4 bottom-3">
                                                <h3 className="font-display text-[1.375rem] leading-tight font-bold text-white">
                                                    {t(
                                                        `welcome.pillars.${key}.title`,
                                                    )}
                                                </h3>
                                                {/* Empty in ar — see `hero.badge_script`. */}
                                                {t(
                                                    `welcome.pillars.${key}.title_script`,
                                                ) && (
                                                    <span
                                                        dir="rtl"
                                                        className="font-arabic text-base text-white/70"
                                                    >
                                                        {t(
                                                            `welcome.pillars.${key}.title_script`,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex flex-1 flex-col p-6">
                                            <p className="text-sm leading-relaxed text-souk-brown-deep/80">
                                                {t(
                                                    `welcome.pillars.${key}.desc`,
                                                )}
                                            </p>

                                            <ul className="mt-4 flex flex-wrap gap-1.5">
                                                {[1, 2, 3, 4].map((n) => (
                                                    <li
                                                        key={n}
                                                        className="rounded-full border border-souk-brown/20 bg-souk-brown/5 px-2.5 py-1 text-[11px] font-semibold tracking-[0.04em] text-souk-brown"
                                                    >
                                                        {t(
                                                            `welcome.pillars.${key}.tag${n}`,
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>

                                            {/* `mt-auto` so the cue sits on the
                                                card's bottom edge whatever the
                                                description's length — the three
                                                translations are not the same
                                                number of lines. */}
                                            <span className="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold text-souk-brown">
                                                {t('welcome.pillars.cta')}
                                                {/*
                                                 * `rtl:rotate-180` because the
                                                 * glyph is a direction, not an
                                                 * ornament: under `dir="rtl"`
                                                 * flex reverses the row so the
                                                 * arrow sits to the *left* of
                                                 * the label, and an unrotated
                                                 * one would then point back at
                                                 * the text it is leading away
                                                 * from. The hover nudge is
                                                 * mirrored for the same reason.
                                                 */}
                                                <ArrowRight
                                                    className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                                                    strokeWidth={1.75}
                                                />
                                            </span>
                                        </div>
                                    </Link>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Harmony ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-peach py-24 lg:py-32">
                    <div className="relative container grid items-center gap-16 lg:grid-cols-[0.92fr_1.08fr]">
                        <div>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2.25rem,4.6vw,4rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('welcome.harmony.heading')}
                                </h2>
                            </Reveal>

                            {/* Numbered rather than indented: the old staggered
                                left margin made the three lines look misaligned
                                instead of deliberate. */}
                            <ol className="mt-10 space-y-6">
                                {[1, 2, 3].map((n, i) => (
                                    <Reveal
                                        key={n}
                                        delay={140 + i * 110}
                                        from="left"
                                    >
                                        <li className="flex gap-5">
                                            <span
                                                aria-hidden="true"
                                                className="mt-1 font-display text-sm text-souk-brown/45 tabular-nums"
                                            >
                                                0{n}
                                            </span>
                                            <p className="border-s border-souk-brown/20 ps-5 text-lg leading-relaxed text-souk-brown-deep">
                                                {t(`welcome.harmony.line${n}`)}
                                            </p>
                                        </li>
                                    </Reveal>
                                ))}
                            </ol>
                        </div>

                        {/* Arch-topped pair — the doorway shape that recurs
                            across the site. */}
                        <Reveal from="right" delay={120}>
                            <div className="relative mx-auto aspect-[5/4] w-full max-w-[34rem]">
                                <div className="absolute top-0 left-0 h-[86%] w-[56%] overflow-hidden rounded-t-full rounded-b-3xl shadow-2xl shadow-souk-brown/25">
                                    <img
                                        src={leather}
                                        alt="Hand-tooled Moroccan leather goods drying in a workshop"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <div className="absolute right-0 bottom-0 h-[66%] w-[48%] overflow-hidden rounded-t-full rounded-b-3xl border-[8px] border-souk-peach shadow-2xl shadow-souk-brown/25">
                                    <img
                                        src={pottery}
                                        alt="Glazed Moroccan pottery arranged on a shelf"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <ZellijStarIcon
                                    aria-hidden="true"
                                    className="absolute top-[8%] right-[10%] h-10 w-10 text-souk-brown/25"
                                />
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────── One workshop, two ways to sell ─────────────
                    Not two cards side by side: the artisan's single shop sits
                    in the middle and the two selling models branch off it, so
                    the composition itself carries the claim — one shop, two
                    kinds of customer. `AUDIENCE` below holds each branch's
                    content; the centre is shared. */}
                <section className="relative overflow-hidden border-y border-souk-brown/10 bg-souk-paper py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-tadelakt motif-fade [--pattern-size:38px]"
                    />

                    <div className="relative container">
                        {/* ── Introduction ── */}
                        <div className="mx-auto max-w-3xl text-center">
                            <Reveal delay={80}>
                                <h2 className="mt-5 font-display text-[clamp(2.25rem,5vw,4.25rem)] leading-[1.02] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('welcome.audience.heading')}
                                </h2>
                            </Reveal>
                            <Reveal delay={160}>
                                <p className="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-souk-brown-deep/75">
                                    {t('welcome.audience.lead')}
                                </p>
                            </Reveal>
                        </div>

                        {/*
                         * The composition. Three tracks at `lg`: branch,
                         * workshop, branch — with the middle column sized to
                         * its content so the two branches stay balanced
                         * either side of it. Below `lg` the same three parts
                         * stack into a vertical sequence, workshop first, so
                         * it reads as a story rather than as a squeezed
                         * desktop layout.
                         */}
                        <div className="mt-20 grid items-center gap-14 lg:mt-24 lg:grid-cols-[1fr_auto_1fr] lg:gap-0">
                            {/* ── Retail branch ── */}
                            <AudienceBranch
                                side="start"
                                model={AUDIENCE.retail}
                                t={t}
                            />

                            {/* ── The workshop itself ── */}
                            <div className="order-first flex flex-col items-center lg:order-none lg:px-10">
                                <Reveal from="scale">
                                    <div className="relative">
                                        {/* Arch-topped, as everywhere else on
                                            the site — the workshop doorway
                                            rather than a rounded rectangle. */}
                                        <div className="relative h-[19rem] w-[14rem] overflow-hidden rounded-t-full rounded-b-lg ring-1 ring-souk-brown/15 sm:h-[22rem] sm:w-[16.5rem]">
                                            <img
                                                src={pottery}
                                                alt={t(
                                                    'welcome.audience.hub_alt',
                                                )}
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                            <div
                                                aria-hidden="true"
                                                className="absolute inset-0 bg-souk-title/15"
                                            />
                                        </div>
                                    </div>
                                </Reveal>

                                <Reveal delay={140}>
                                    <p className="mt-7 max-w-[13rem] text-center font-display text-lg leading-snug text-souk-title">
                                        {t('welcome.audience.hub')}
                                    </p>
                                    <span
                                        aria-hidden="true"
                                        className="mx-auto mt-4 block h-px w-10 bg-souk-brown/30"
                                    />
                                </Reveal>
                            </div>

                            {/* ── Wholesale branch ── */}
                            <AudienceBranch
                                side="end"
                                model={AUDIENCE.wholesale}
                                t={t}
                            />
                        </div>

                        <Reveal delay={220}>
                            <div className="mt-20 text-center">
                                <Link
                                    href="/seller/onboarding"
                                    className="group inline-flex items-center gap-3 rounded-lg bg-souk-brown px-9 py-4 font-display text-lg text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep"
                                >
                                    {t('welcome.audience.cta')}
                                    <ArrowRight
                                        className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── Where they work ─────────────────────
                    A diagram, not a map service: a flat silhouette with four
                    pins. Nothing here is tiled, geocoded or fetched, so the
                    section costs one inline path and no third-party script. */}
                <section className="relative overflow-hidden bg-souk-peach py-24 lg:py-32">
                    <div className="relative z-10 container">
                        {/*
                         * Copy beside the map rather than stacked above it, so
                         * the two halves travel toward each other as the
                         * section scrolls in.
                         *
                         * Under `dir="rtl"` the copy moves to the other side,
                         * so the reveal directions are swapped with it —
                         * `Reveal`'s `from` is a physical translate, and
                         * left/right would otherwise be the wrong way round
                         * in Arabic, each half sliding in across the other.
                         */}
                        <div className="lg:w-[50%]">
                            <Reveal from={isRTL ? 'right' : 'left'}>
                                {/* <p className="text-[11px] font-semibold tracking-[0.24em] text-souk-brown uppercase">
                                    {t('welcome.map.eyebrow')}
                                </p> */}

                                <h2 className="mt-4 font-display text-[clamp(2rem,4.2vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.02em] text-souk-title">
                                    <span className="block">
                                        {t('welcome.map.heading_line1')}
                                    </span>
                                    <span className="block text-souk-brown italic">
                                        {t('welcome.map.heading_line2')}
                                    </span>
                                </h2>

                                <p className="mt-6 max-w-md text-lg leading-relaxed text-souk-brown-deep/80">
                                    {t('welcome.map.lead')}
                                </p>
                                <p className="mt-4 max-w-md leading-relaxed text-souk-brown-deep/70">
                                    {t('welcome.map.body')}
                                </p>

                                {/* The pins, restated as a list — the map's
                                    own labels are small, and this gives the
                                    four names a readable form on a phone
                                    where the panel shrinks. */}
                                {/* <ul className="mt-8 flex flex-wrap gap-2">
                                    {MAP_CITIES.map(({ key }) => (
                                        <li
                                            key={key}
                                            className="inline-flex items-center gap-2 rounded-full border border-souk-brown/20 bg-souk-brown/5 px-3.5 py-1.5 text-sm text-souk-brown-deep"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="h-1.5 w-1.5 shrink-0 rounded-full bg-souk-brown"
                                            />
                                            {t(`welcome.map.city.${key}`)}
                                        </li>
                                    ))}
                                </ul> */}
                            </Reveal>
                        </div>
                    </div>

                    {/*
                     * The map, as the section's own side background.
                     *
                     * One element in two modes rather than two copies: below
                     * `lg` it is an ordinary block under the copy, and from
                     * `lg` it becomes absolute and full-bleed against the
                     * trailing edge. `inset-y-0` sets its height from the
                     * section, and the aspect ratio derives the width — so the
                     * artwork is never letterboxed and the pins, which are
                     * percentages of this same box, stay in register in both
                     * modes.
                     *
                     * `end-0` is logical, so the map moves to the other side
                     * in Arabic along with the copy. The artwork inside does
                     * not mirror — only which edge it hangs off.
                     */}
                    <Reveal
                        from={isRTL ? 'left' : 'right'}
                        delay={120}
                        className="relative z-0 mt-14 px-6 lg:absolute lg:inset-y-0 lg:end-0 lg:mt-0 lg:px-0"
                    >
                        <div
                            className="relative mx-auto w-full max-w-xs lg:h-full lg:w-auto lg:max-w-none"
                            style={{ aspectRatio: MOROCCO_ASPECT }}
                        >
                            <MoroccoMap
                                className="h-full w-full text-souk-brown"
                                fillOpacity={0.14}
                                strokeOpacity={0.4}
                            />

                            {/*
                             * Pins are HTML over the SVG rather than
                             * <circle>/<text> inside it: that way the
                             * labels are real text in the page font and
                             * `motion-safe:` can switch the pulse off,
                             * neither of which SMIL inside the SVG would
                             * give.
                             *
                             * `left`/`top` are physical on purpose. Every
                             * other offset on this page is logical so it
                             * mirrors under `dir="rtl"` — a map must not.
                             * Tangier is north whichever way the page
                             * reads.
                             */}
                            {MAP_CITIES.map(
                                ({ key, lon, lat, labelAbove }, i) => {
                                    const { left, top } = projectMorocco(
                                        lon,
                                        lat,
                                    );

                                    return (
                                        <div
                                            key={key}
                                            className="absolute -translate-x-1/2 -translate-y-1/2"
                                            style={{
                                                left: `${left}%`,
                                                top: `${top}%`,
                                            }}
                                        >
                                            <span className="relative flex h-2.5 w-2.5">
                                                <span
                                                    className="absolute inline-flex h-full w-full rounded-full bg-souk-brown/60 motion-safe:animate-ping"
                                                    /* Offset so the four pulse in
                                                   turn rather than as one
                                                   flashing block. */
                                                    style={{
                                                        animationDelay: `${i * 600}ms`,
                                                    }}
                                                />
                                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-souk-brown ring-2 ring-souk-peach" />
                                            </span>

                                            <span
                                                className={`absolute left-1/2 -translate-x-1/2 font-display text-xs font-semibold whitespace-nowrap text-souk-brown-deep sm:text-sm ${
                                                    labelAbove
                                                        ? 'bottom-4'
                                                        : 'top-4'
                                                }`}
                                            >
                                                {t(`welcome.map.city.${key}`)}
                                            </span>
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    </Reveal>
                </section>

                {/* ───────────────────── Gallery ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-paper py-24 lg:py-28">
                    <div className="relative container text-center">
                        <Reveal delay={80}>
                            <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-none font-bold tracking-[-0.02em] text-souk-title">
                                {t('welcome.gallery.heading')}
                            </h2>
                        </Reveal>
                        <Reveal delay={140}>
                            <p className="mx-auto mt-4 max-w-xl text-souk-brown-deep/75">
                                {t('welcome.gallery.lead')}
                            </p>
                        </Reveal>
                    </div>

                    {/* Full-bleed rather than contained: the strip has to run
                        off both edges for the drift to read as continuous
                        rather than as a row that stops. */}
                    <div className="relative mt-14 overflow-hidden">
                        {/*
                         * `motion-safe:` as on the word marquee — anyone who
                         * has asked their OS for reduced motion gets a still
                         * strip instead. Pauses on hover so a piece that
                         * catches the eye can actually be looked at.
                         */}
                        <div className="flex w-max hover:[animation-play-state:paused] motion-safe:animate-gallery">
                            {[0, 1].map((copy) => (
                                <ul
                                    key={copy}
                                    className="flex items-center"
                                    /*
                                     * The loop needs two identical copies to
                                     * translate across seamlessly, but a
                                     * screen reader should hear the set once.
                                     */
                                    aria-hidden={copy === 1 || undefined}
                                >
                                    {GALLERY.map(({ src, alt, box, shift }) => (
                                        <li
                                            key={`${copy}-${alt}`}
                                            className={`px-2 md:px-3 ${shift}`}
                                        >
                                            <div
                                                className={`group relative w-44 overflow-hidden rounded-3xl bg-souk-brown/5 ring-1 ring-souk-brown/10 md:w-60 ${box}`}
                                            >
                                                <img
                                                    src={src}
                                                    alt={copy === 0 ? alt : ''}
                                                    className="h-full w-full object-cover transition-transform duration-[900ms] group-hover:scale-105"
                                                    loading="lazy"
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ))}
                        </div>

                        {/*
                         * Feathered edges, so tiles enter and leave the strip
                         * instead of being clipped off mid-drift against a
                         * hard border.
                         */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-y-0 start-0 w-16 bg-gradient-to-r from-souk-paper to-transparent md:w-28 rtl:bg-gradient-to-l"
                        />
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-y-0 end-0 w-16 bg-gradient-to-l from-souk-paper to-transparent md:w-28 rtl:bg-gradient-to-r"
                        />
                    </div>
                </section>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
