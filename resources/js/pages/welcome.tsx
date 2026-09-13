import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Download,
    Mail,
    MapPin,
    Phone,
} from 'lucide-react';
import { useRef, useState } from 'react';
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
import HeroSlider from '@/components/home/HeroSlider';
import MoroccoMap, {
    MOROCCO_ASPECT,
    projectMorocco,
} from '@/components/home/MoroccoMap';
import type {
    ProductRailBounds,
    ProductRailHandle,
    RailProduct,
} from '@/components/home/ProductRail';
import ProductRail from '@/components/home/ProductRail';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import brochure from '@/images/brochure/brochure.jpeg';
import jewelry from '@/images/cat-jewelry.jpg';
import leather from '@/images/cat-leather.jpg';
import pottery from '@/images/cat-pottery.jpg';
import spice from '@/images/cat-spice.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';
import exploreArt from '@/images/categories/art.jpeg';
import exploreLantern from '@/images/categories/lantern.jpeg';
import explorePottery from '@/images/categories/pottery.jpeg';
import exploreTextile from '@/images/categories/textile.jpeg';
import exploreLeather from '@/images/categories/lether.jpeg';
import exploreWood from '@/images/categories/wood.jpeg';
import hero1 from '@/images/hero/hero-1.jpeg';
import hero2 from '@/images/hero/hero-2.jpg';
import hero3 from '@/images/hero/hero-3.jpg';

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
    { src: hero1, altKey: 'welcome.hero.alt' },
    { src: hero2, altKey: 'welcome.hero.alt_textile' },
    { src: hero3, altKey: 'welcome.hero.alt_zellige' },
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
 * The "Explore by category" tiles under the hero.
 *
 * `slugMatch` finds each tile's real `Category` row inside the shared
 * `navCategories` prop (see `HandleInertiaRequests::getNavCategories()`),
 * rather than hardcoding an id: seed order is not a contract, and an id
 * baked in here would silently point at the wrong craft — or at nothing —
 * the moment a store re-seeds. Matched against the *French* seed name via
 * `Str::slug()`, because the slug is the one string `CategorySeeder` fixes
 * as stable across locales; `translated_name` moves with the active one.
 *
 * `key` doubles as the translation suffix for the fallback label
 * (`welcome.categories_grid.<key>`), used only if the category is not found —
 * an empty or freshly seeded database should still render every tile rather
 * than a dead link.
 */
const EXPLORE_CATEGORIES = [
    { key: 'pottery', image: explorePottery, slugMatch: 'poterie' },
    { key: 'textile', image: exploreTextile, slugMatch: 'textile' },
    { key: 'art', image: exploreArt, slugMatch: 'peinture' },
    { key: 'lantern', image: exploreLantern, slugMatch: 'lanterne' },
    { key: 'wood', image: exploreWood, slugMatch: 'bois' },
    { key: 'leather', image: exploreLeather, slugMatch: 'leather' },
] as const;

/**
 * The three ways to reach Hirfaty, as content rather than markup — the rows
 * are structurally identical, so describing them as data keeps the single row
 * template below honest about that.
 *
 * `key` is the translation suffix for both halves of the row: the value lives
 * at `welcome.contact.<key>` and its label at `welcome.contact.<key>_label`,
 * so a row's copy is found in one place.
 *
 * `href` is the scheme the value is prefixed with to make it a link. Absent on
 * the address, which is the one row with nothing to activate — rendering it as
 * an anchor would promise a map that does not open.
 */
const CONTACT_ROWS = [
    { key: 'email', Icon: Mail, href: 'mailto:' },
    { key: 'phone', Icon: Phone, href: 'tel:' },
    { key: 'address', Icon: MapPin, href: null },
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
    // { key: 'meknes', lat: 33.8935, lon: -5.5473, labelAbove: true },
    { key: 'safi', lat: 32.2994, lon: -9.2372, labelAbove: false },
    { key: 'marrakech', lat: 31.6295, lon: -7.9811, labelAbove: false },
    { key: 'agadir', lat: 30.4278, lon: -9.5981, labelAbove: false },
    { key: 'laayoune', lat: 27.1536, lon: -13.2033, labelAbove: false },
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

    // Shared by every page (see `HandleInertiaRequests`), not fetched here —
    // the header already depends on the same prop, so the home page reads it
    // rather than asking the controller for a second, page-specific copy.
    const { navCategories } = usePage<{
        navCategories?: Array<{
            id: number;
            slug: string;
            translated_name: string;
        }>;
    }>().props;

    const categoryTiles = EXPLORE_CATEGORIES.map((tile) => {
        const category = navCategories?.find((c) =>
            c.slug.includes(tile.slugMatch),
        );

        return {
            ...tile,
            href: category
                ? `/products?category_id=${category.id}`
                : '/products',
            label:
                category?.translated_name ??
                t(`welcome.categories_grid.${tile.key}`),
        };
    });

    /*
     * The product rail's own paging, driven from here rather than from
     * inside the rail: the arrows now sit top-right of this section next to
     * "See all products", not overlaid on the rail's photography, so this
     * component needs a handle to call `prev`/`next` on and a copy of the
     * rail's bounds to grey them out at the ends. See `ProductRail`'s own
     * doc for why this moved rather than staying self-contained.
     */
    const railRef = useRef<ProductRailHandle>(null);
    const [railBounds, setRailBounds] = useState<ProductRailBounds>({
        atStart: true,
        atEnd: false,
        autoplay: true,
    });

    /*
     * The arch shape SiteHeader's own icon buttons use (rounded on top,
     * squared at the foot), so this pair reads as belonging to the same
     * button vocabulary rather than as a one-off control invented for this
     * section.
     */
    const railArrowButton =
        'grid h-9 w-9 cursor-pointer shrink-0 place-items-center rounded-t-full rounded-b-md text-souk-brown ring-1 ring-souk-brown/25 transition-colors hover:bg-souk-brown hover:text-souk-cream disabled:pointer-events-none disabled:opacity-30';

    return (
        <>
            <Head title="Hirfaty — Moroccan craft, direct from the artisans" />

            <div className="min-h-screen bg-souk-paper">
                {/* In flow and cream-filled, not pinned over the hero: the
                    hero band no longer reaches the top of the page — it is
                    inset, with paper showing around it — so a transparent bar
                    would put white type on white paper. */}
                <SiteHeader />

                {/* ───────────────────────── Hero ─────────────────────────
                    Three photographs the visitor pages *sideways* through,
                    rather than one still or a dissolve: the band reads as a
                    strip of the catalogue that continues past the edge, which
                    is the promise the arrows make.

                    Inset from the page on the sides and underneath so the
                    paper frames it — the band is a plate on the page, not the
                    page itself. The slider carries its own scrim and controls;
                    see HeroSlider. */}
                {/* Sides and foot only — no top padding, so the band sits
                    directly under the bar and the margin reads as even on the
                    three edges that have one.

                    `overflow-x-clip` because the arrows are rotated squares
                    overhanging into the margin: their bounding boxes are
                    wider than the squares themselves, and on a phone (where
                    the margin is narrowest) that pushed ~9px of horizontal
                    scroll onto the whole page. `clip` rather than `hidden`
                    so no scroll container is created, and on the x axis only
                    so `overflow-y` stays `visible`. */}
                <section className="overflow-x-clip bg-souk-paper px-5 pb-5 sm:px-8 sm:pb-8 lg:px-12 lg:pb-12">
                    <HeroSlider
                        slides={HERO_SLIDES.map((slide) => ({
                            src: slide.src,
                            alt: t(slide.altKey),
                        }))}
                        label={t('welcome.hero.slides_label')}
                        prevLabel={t('welcome.hero.prev')}
                        nextLabel={t('welcome.hero.next')}
                        isRTL={isRTL}
                    >
                        {/*
                         * `tracking` is cancelled under `dir="rtl"` on both the
                         * heading and the eyebrow: Arabic is a joined script,
                         * and letter-spacing it prises the connected forms
                         * apart into unreadable single letters.
                         */}
                        {/* Capped at 4rem rather than pushed as large as the
                            band allows: the two claim lines are a sentence
                            split in half, and at a bigger size the longer
                            locales wrap the first half again — which reads as
                            three unrelated lines rather than as one thought. */}
                        <h1 className="font-display text-[clamp(1.75rem,4.8vw,4rem)] leading-[1.08] tracking-[0.04em] text-white uppercase rtl:tracking-normal">
                            <Reveal delay={80}>
                                <span className="block">
                                    {t('welcome.hero.line1')}
                                </span>
                            </Reveal>
                            <Reveal delay={180}>
                                <span className="block text-souk-cream">
                                    {t('welcome.hero.line2')}
                                </span>
                            </Reveal>
                        </h1>

                        <Reveal delay={280}>
                            {/* `mx-auto` on a narrower measure than the
                                heading: the lead is body copy, and centred
                                prose set to the full width of a 4xl band is
                                unreadable however short it is. */}
                            <p className="mx-auto mt-6 max-w-xl text-base leading-relaxed text-souk-cream/90 italic sm:text-lg">
                                {t('welcome.hero.lead')}
                            </p>
                        </Reveal>

                        <Reveal delay={360}>
                            {/* `pointer-events-auto` re-arms just the buttons:
                                the copy layer above them is deliberately
                                click-through so a swipe can start on the
                                headline. */}
                            <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
                                <Link
                                    href="/products"
                                    className="group pointer-events-auto relative inline-flex items-center gap-2.5 overflow-hidden rounded-xl bg-souk-brown px-7 py-3.5 font-semibold text-souk-cream shadow-lg shadow-black/25 hover:bg-souk-brown/90"
                                >
                                    <StorefrontIcon className="h-5 w-5 shrink-0" />
                                    {t('welcome.hero.cta_primary')}
                                </Link>
                                <Link
                                    href="/seller/onboarding"
                                    className="pointer-events-auto inline-flex items-center gap-2.5 rounded-xl border-[1.5px] border-souk-cream/40 bg-souk-cream/15 px-7 py-3.5 font-semibold text-souk-cream backdrop-blur transition-colors duration-300 hover:bg-souk-cream/25"
                                >
                                    <AtelierArchIcon className="h-5 w-5 shrink-0" />
                                    {t('welcome.hero.cta_secondary')}
                                </Link>
                            </div>
                        </Reveal>
                    </HeroSlider>
                </section>

                {/* ─────────────── Explore by categories ───────────────
                    Crafts as photographs rather than the categories menu's
                    flat text list: this is the first thing under the hero,
                    and a name alone asks a visitor to already know what
                    "lantern-making" looks like. A handful rather than the
                    full set behind the header's own menu — a grid deep
                    enough to browse would duplicate the /products filters
                    this is meant to be a doorway into. */}
                <section className="bg-souk-paper py-8 lg:py-8">
                    <div className="container">
                        <Reveal delay={140}>
                            <h2 className="mt-4 text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                                {t('welcome.categories_grid.heading')}
                            </h2>
                        </Reveal>

                        <div className="mt-14 grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                            {categoryTiles.map((tile, i) => (
                                <Reveal key={tile.key} delay={200 + i * 100}>
                                    <Link
                                        href={tile.href}
                                        className="group relative block aspect-[4/5] overflow-hidden rounded-xl bg-souk-brown/5"
                                    >
                                        <img
                                            src={tile.image}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                        {/* Scrim only at the foot, where the
                                            label sits — the rest of the
                                            photograph stays true, since
                                            nothing else on the tile needs to
                                            read over it. */}
                                        <div
                                            aria-hidden="true"
                                            className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-souk-title/85 via-souk-title/25 to-transparent"
                                        />
                                        <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 p-5">
                                            <span className="font-display text-lg text-souk-cream">
                                                {tile.label}
                                            </span>
                                            <ArrowRight
                                                className="h-4 w-4 shrink-0 text-souk-cream transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                                                strokeWidth={1.75}
                                            />
                                        </div>
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 ring-1 ring-souk-brown/10 ring-inset"
                                        />
                                    </Link>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────── Explore our products ─────────────────
                    Directly under the hero: the first thing a visitor should
                    see is what is actually for sale, not an explanation of
                    the marketplace. A rail rather than a grid so a full row
                    of stock is reachable without the section swallowing a
                    screen and a half. */}
                <section className="relative overflow-hidden bg-souk-paper py-8 lg:py-8">
                    <div className="te relative container">
                        <div className="flex flex-col">
                            <div className="mx-auto w-full max-w-2xl text-center">
                                <Reveal delay={80}>
                                    <h2 className="mt-4 text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                                        {t('welcome.products.heading')}
                                    </h2>
                                </Reveal>
                            </div>
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-4 sm:mt-0 sm:ml-auto sm:justify-end sm:gap-6">
                                {/* Arrows grouped with "See all products" rather
                                than left overlaid on the rail itself: both
                                are ways to move through the same catalogue,
                                so they read as one control cluster, top-right
                                of the section on larger screens — centered
                                under the heading on narrow ones where there
                                isn't room to push it to the edge. */}
                                <Reveal delay={200}>
                                    <div className="flex flex-wrap items-center justify-center gap-4 sm:flex-nowrap sm:gap-5">
                                        {/* Matches the ternary below: no rail
                                        mounted, nothing for these to
                                        control. */}
                                        {featuredProducts.length > 0 && (
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        railRef.current?.prev()
                                                    }
                                                    disabled={
                                                        !railBounds.autoplay &&
                                                        railBounds.atStart
                                                    }
                                                    aria-label={t(
                                                        'welcome.products.prev',
                                                    )}
                                                    className={railArrowButton}
                                                >
                                                    <ChevronLeft
                                                        className="h-4 w-4 rtl:rotate-180"
                                                        strokeWidth={1.75}
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        railRef.current?.next()
                                                    }
                                                    disabled={
                                                        !railBounds.autoplay &&
                                                        railBounds.atEnd
                                                    }
                                                    aria-label={t(
                                                        'welcome.products.next',
                                                    )}
                                                    className={railArrowButton}
                                                >
                                                    <ChevronRight
                                                        className="h-4 w-4 rtl:rotate-180"
                                                        strokeWidth={1.75}
                                                    />
                                                </button>
                                            </div>
                                        )}
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
                                    </div>
                                </Reveal>
                            </div>
                        </div>
                        {featuredProducts.length > 0 ? (
                            <div className="mt-14">
                                <ProductRail
                                    ref={railRef}
                                    products={featuredProducts}
                                    isRTL={isRTL}
                                    currency={t('welcome.products.currency')}
                                    onBoundsChange={setRailBounds}
                                />
                            </div>
                        ) : (
                            <div className="mt-14">
                                <div className="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                                    {SHOWCASE.slice(0, 4).map((src, i) => (
                                        <Reveal key={src} delay={i * 100}>
                                            <div className="relative aspect-[4/5] overflow-hidden bg-souk-brown/5">
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

                {/* ───────────────────── Product catalogue ─────────────────────
                    A slim promo bar rather than a full section: this is a
                    single, secondary action (download a PDF), not a pitch
                    that needs its own heading hierarchy and lead paragraph —
                    it should read as a strip, the way the site's flash
                    banners do, not as a peer of the sections either side of
                    it.
                    The photograph of the catalogue fills the trailing half of
                    the panel as its background rather than sitting in the copy
                    as a thumbnail: at icon size it read as a grey smudge, and
                    at this size it does the job the copy otherwise has to —
                    showing there is a real, printed object behind the
                    download.
                    Download wiring is deliberately not here yet — this is the
                    design only, the button has nothing to point at until a
                    catalogue file and a route exist. */}
                <section className="py-8 lg:py-8">
                    <div className="container">
                        <Reveal from="scale">
                            <div className="relative isolate overflow-hidden rounded-2xl bg-souk-brown">
                                {/*
                                 * `end-0` rather than `right-0`: the copy
                                 * beside it is text, so the whole panel
                                 * mirrors under `dir="rtl"` and the
                                 * photograph has to travel with it — the
                                 * picture would otherwise end up behind the
                                 * Arabic.
                                 *
                                 * `lg` and up only, and at exactly half.
                                 * Below that the panel is too narrow to give
                                 * away a half: it showed at 3/5 from `sm`,
                                 * and since the copy was only capped by
                                 * `max-w-xl` the heading ran a third of the
                                 * way into the picture, where the scrim has
                                 * already faded to ~60% and the type stopped
                                 * being reliably legible. The copy takes the
                                 * matching `lg:w-1/2` below, so the two
                                 * halves meet without overlapping at any
                                 * width.
                                 */}
                                <div
                                    aria-hidden="true"
                                    className="absolute inset-y-0 end-0 -z-10 hidden lg:block lg:w-1/2"
                                >
                                    <img
                                        src={brochure}
                                        alt=""
                                        loading="lazy"
                                        className="h-full w-full object-cover"
                                    />
                                    {/*
                                     * Dissolves the photograph's leading edge
                                     * into the brown so the two do not meet on
                                     * a hard seam. A horizontal gradient is a
                                     * physical direction, so it is flipped
                                     * explicitly for Arabic — `to-l` fades
                                     * leftward (brown at the left, clear at
                                     * the right), which is only correct while
                                     * the picture sits on the right.
                                     */}
                                    <div className="absolute inset-0 bg-gradient-to-l from-transparent via-souk-brown/55 to-souk-brown rtl:bg-gradient-to-r" />
                                </div>

                                {/* `lg:w-1/2` is what actually keeps the copy
                                    off the photograph — `max-w-xl` alone is a
                                    fixed 36rem, so on a panel narrower than
                                    72rem it stopped short of the constraint
                                    and the type ran under the picture. Half
                                    the panel is the same boundary the photo
                                    uses, so they cannot overlap however long
                                    a translation runs. */}
                                <div className="max-w-xl px-6 py-10 sm:px-8 sm:py-12 lg:w-1/2 lg:py-14">
                                    <p className="font-display text-2xl leading-snug text-white">
                                        {t('welcome.catalogue.heading')}
                                    </p>
                                    <p className="mt-1.5 text-sm text-souk-cream/75">
                                        {t('welcome.catalogue.lead')}
                                    </p>
                                    <a
                                        href="#"
                                        onClick={(e) => e.preventDefault()}
                                        className="mt-7 inline-flex items-center justify-center gap-2 rounded-xl bg-souk-cream px-6 py-3 text-sm font-semibold tracking-[0.04em] text-souk-brown-deep uppercase transition-colors hover:bg-white"
                                    >
                                        <Download
                                            className="h-4 w-4 shrink-0"
                                            strokeWidth={2}
                                        />
                                        {t('welcome.catalogue.button')}
                                    </a>
                                </div>
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
                <section className="relative overflow-hidden border-y border-souk-brown/10 bg-souk-paper py-8 lg:py-8">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-tadelakt motif-fade [--pattern-size:38px]"
                    />

                    <div className="relative container">
                        {/* ── Introduction ── */}
                        <div className="mx-auto max-w-3xl text-center">
                            <Reveal delay={80}>
                                <h2 className="mt-5 font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.02] font-bold tracking-[-0.02em] text-souk-title">
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

                {/* ───────────────────────── Contact ─────────────────────────
                    The map stays, but as the companion to the contact details
                    rather than as the section's own full-bleed background: it
                    used to hang off the trailing edge with the copy half a
                    screen away, which read as two unrelated things sharing a
                    band. In a two-column grid the pins sit directly beside the
                    address they illustrate.

                    Still a diagram, not a map service — a flat silhouette with
                    pins, so this costs one inline path and no third-party
                    script. */}
                <section className="relative overflow-hidden">
                    <div className="container">
                        {/*
                         * `items-center` so the map sits against the middle of
                         * the details rather than the top of them, and the two
                         * columns read as a pair.
                         *
                         * Under `dir="rtl"` the grid mirrors, so the reveal
                         * directions are swapped with it — `Reveal`'s `from` is
                         * a physical translate, and left/right would otherwise
                         * be the wrong way round in Arabic, each half sliding
                         * in across the other.
                         */}
                        <div className="mx-auto grid w-fit items-center gap-14 py-8 lg:grid-cols-2 lg:gap-8">
                            <Reveal from={isRTL ? 'right' : 'left'}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4.2vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.02em] text-souk-title">
                                    <span className="block">
                                        {t('welcome.contact.heading_line1')}
                                    </span>
                                    <span className="block text-souk-brown">
                                        {t('welcome.contact.heading_line2')}
                                    </span>
                                </h2>

                                {/*
                                 * A `<dl>`, not a list of paragraphs: each row
                                 * is a label and its value, which is what a
                                 * description list is for — and it gives a
                                 * screen reader the pairing for free.
                                 *
                                 * Email and phone are real links; an address
                                 * is not, so it stays plain text rather than
                                 * being dressed as something clickable.
                                 */}
                                <dl className="mt-10 space-y-7">
                                    {CONTACT_ROWS.map(({ key, Icon, href }) => {
                                        const value = t(
                                            `welcome.contact.${key}`,
                                        );
                                        /*
                                         * The number is stored spaced for
                                         * legibility ("+212 000 000 000"), but
                                         * a `tel:` URI has no spaces in it —
                                         * RFC 3966 wants the digits and the
                                         * `+` and nothing else. Stripped here
                                         * rather than in the translation so
                                         * the displayed value keeps its
                                         * grouping.
                                         */
                                        const target = href
                                            ? `${href}${
                                                  href === 'tel:'
                                                      ? value.replace(
                                                            /[^+\d]/g,
                                                            '',
                                                        )
                                                      : value
                                              }`
                                            : null;

                                        return (
                                            <div
                                                key={key}
                                                className="flex items-start gap-4"
                                            >
                                                {/* The arch-topped frame the
                                                    header's icon buttons and
                                                    the product cards wear, so
                                                    these belong to the same
                                                    vocabulary. */}
                                                <span
                                                    aria-hidden="true"
                                                    className="grid size-11 shrink-0 place-items-center rounded-t-full rounded-b-md bg-souk-brown/10 text-souk-brown ring-1 ring-souk-brown/15"
                                                >
                                                    <Icon
                                                        className="size-[18px]"
                                                        strokeWidth={1.6}
                                                    />
                                                </span>

                                                <div>
                                                    <dt className="text-[11px] font-semibold tracking-[0.18em] text-souk-brown-deep/55 uppercase rtl:tracking-normal">
                                                        {t(
                                                            `welcome.contact.${key}_label`,
                                                        )}
                                                    </dt>
                                                    {/*
                                                     * `dir="ltr"` on the value:
                                                     * an email address and a
                                                     * dialling code are Latin
                                                     * and left-to-right even
                                                     * inside Arabic, and a
                                                     * leading `+` in an RTL
                                                     * paragraph is reordered to
                                                     * the wrong end of the
                                                     * number without it.
                                                     */}
                                                    <dd
                                                        dir={
                                                            target
                                                                ? 'ltr'
                                                                : undefined
                                                        }
                                                        className="mt-1 font-display text-lg text-souk-title rtl:text-end"
                                                    >
                                                        {target ? (
                                                            <a
                                                                href={target}
                                                                className="transition-colors hover:text-souk-brown"
                                                            >
                                                                {value}
                                                            </a>
                                                        ) : (
                                                            value
                                                        )}
                                                    </dd>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </dl>
                            </Reveal>

                            {/*
                             * The map. In flow beside the details now rather
                             * than absolutely positioned against the section
                             * edge — the aspect ratio still derives the height
                             * from the width, so the artwork is never
                             * letterboxed and the pins, which are percentages
                             * of this same box, stay in register.
                             */}
                            <Reveal from={isRTL ? 'left' : 'right'} delay={120}>
                                {/*
                                 * `lg:mx-0` pulls the artwork to the inline
                                 * *start* of its column from `lg` up, rather
                                 * than centring it there: centred, the map's
                                 * own leftover column space stacked on top of
                                 * the grid gap and left it 140px from the
                                 * details it belongs to. Hugging the start
                                 * edge leaves only the gap itself.
                                 *
                                 * Inline-start, not `left`, so it still sits
                                 * on the side facing the details once the grid
                                 * mirrors under `dir="rtl"`. Centred below
                                 * `lg`, where the two stack and there is no
                                 * column to hug.
                                 */}
                                <div
                                    className="relative mx-auto w-full max-w-sm lg:mx-0 lg:max-w-md"
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
                                            const { left, top } =
                                                projectMorocco(lon, lat);

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
                                                        {t(
                                                            `welcome.map.city.${key}`,
                                                        )}
                                                    </span>
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                            </Reveal>
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Gallery ───────────────────── */}

                <div>logos</div>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
