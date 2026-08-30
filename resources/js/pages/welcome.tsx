import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Building2, Check, Store } from 'lucide-react';
import ArtisanCta from '@/components/ArtisanCta';
import Reveal from '@/components/home/Reveal';
import VaseIcon from '@/components/home/VaseIcon';
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
import zellij from '@/images/zellij-pattern.png';

type Media = { id: number; url: string; type: string };

type FeaturedProduct = {
    id: number;
    name: string;
    slug: string;
    base_price: number | string;
    sale_price: number | string | null;
    category: { id: number | null; name: string | null };
    store: { store_name: string | null; city_name: string | null };
    images: Media[];
};

/** Fallback tiles for the product strip before any product is published. */
const SHOWCASE = [pottery, leather, wood, textile, jewelry, spice];

const MARQUEE = [
    'Pottery',
    'Zellige',
    'Leather',
    'Cedar wood',
    'Brass',
    'Wool rugs',
    'Babouches',
    'Argan',
    'Silver',
    'Raffia',
];

export default function Welcome({
    featuredProducts = [],
}: {
    canRegister?: boolean;
    featuredProducts?: FeaturedProduct[];
}) {
    const { t } = useTranslation();

    const price = (p: FeaturedProduct) => Number(p.sale_price ?? p.base_price);

    return (
        <>
            <Head title="Hirfaty — Moroccan craft, direct from the artisans" />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <section className="relative overflow-hidden">
                    <div className="relative bg-souk-brown lg:rounded-br-[40rem]">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 opacity-[0.07] mix-blend-screen"
                            style={{
                                backgroundImage: `url(${zellij})`,
                                backgroundSize: '340px',
                            }}
                        />

                        <div className="relative container grid items-center gap-14 py-20 lg:grid-cols-[1.05fr_0.95fr] lg:py-28">
                            {/* Copy */}
                            <div>
                                {/* <Reveal>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-souk-cream/30 bg-souk-cream/10 px-4 py-1.5 text-[11px] tracking-[0.18em] text-souk-cream uppercase backdrop-blur">
                                        <Sparkles
                                            className="h-3.5 w-3.5"
                                            strokeWidth={1.5}
                                        />
                                        {t('welcome.hero.badge')}
                                    </span>
                                </Reveal> */}

                                <h1 className="mt-7 font-display text-[clamp(3rem,8vw,7rem)] leading-[0.92] tracking-[-0.02em] text-white">
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
                                    <p className="mt-7 max-w-lg text-lg leading-relaxed text-souk-cream/85">
                                        {t('welcome.hero.lead')}
                                    </p>
                                </Reveal>

                                <Reveal delay={360}>
                                    <div className="mt-10 flex flex-wrap items-center gap-4">
                                        <Link
                                            href="/products"
                                            className="group relative inline-flex items-center gap-3 overflow-hidden rounded-xl bg-souk-cream px-8 py-4 font-display text-lg text-souk-brown-deep shadow-lg shadow-black/10 transition-transform duration-300 hover:-translate-y-0.5"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="absolute inset-y-0 -left-1/3 w-1/3 bg-white/50 opacity-0 group-hover:animate-sheen group-hover:opacity-100"
                                            />
                                            {t('welcome.hero.cta_primary')}
                                            <ArrowRight
                                                className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
                                                strokeWidth={1.75}
                                            />
                                        </Link>
                                        <Link
                                            href="/seller/onboarding"
                                            className="inline-flex items-center gap-2 rounded-xl border border-souk-cream/40 px-7 py-4 text-souk-cream transition-colors duration-300 hover:bg-souk-cream/10"
                                        >
                                            {t('welcome.hero.cta_secondary')}
                                        </Link>
                                    </div>
                                </Reveal>

                                <Reveal delay={440}>
                                    <dl className="mt-14 grid max-w-lg grid-cols-3 gap-6 border-t border-souk-cream/20 pt-8">
                                        {[
                                            [
                                                '120+',
                                                t('welcome.hero.stat_artisans'),
                                            ],
                                            [
                                                '14',
                                                t('welcome.hero.stat_cities'),
                                            ],
                                            [
                                                '100%',
                                                t('welcome.hero.stat_handmade'),
                                            ],
                                        ].map(([value, label]) => (
                                            <div key={label}>
                                                <dt className="font-display text-3xl text-white">
                                                    {value}
                                                </dt>
                                                <dd className="mt-1 text-xs leading-snug text-souk-cream/70">
                                                    {label}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </Reveal>
                            </div>

                            {/* Arch-framed hero image */}
                            <Reveal
                                from="scale"
                                delay={200}
                                className="relative"
                            >
                                <div className="relative mx-auto w-full max-w-[30rem]">
                                    {/* offset outline arch, as drawn in the design */}
                                    <div
                                        aria-hidden="true"
                                        className="absolute -top-6 -right-5 h-full w-full rounded-t-full rounded-bl-3xl border-[3px] border-souk-cream/60"
                                    />
                                    <div className="relative aspect-[530/686] w-full overflow-hidden rounded-t-full rounded-bl-3xl shadow-2xl shadow-black/25 motion-safe:animate-float-slow">
                                        <img
                                            src={heroMain}
                                            alt="A Moroccan artisan shaping a clay vessel by hand"
                                            className="h-full w-full object-cover"
                                            loading="eager"
                                        />
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 bg-gradient-to-t from-souk-brown/45 via-transparent to-transparent"
                                        />
                                    </div>

                                    {/* floating credential chip */}
                                    <div className="absolute -bottom-5 left-0 flex items-center gap-3 rounded-2xl bg-souk-paper/95 px-5 py-3.5 shadow-xl shadow-black/15 backdrop-blur sm:-left-8">
                                        <BadgeCheck
                                            className="h-6 w-6 shrink-0 text-souk-brown"
                                            strokeWidth={1.5}
                                        />
                                        <div className="leading-tight">
                                            <p className="font-display text-sm text-souk-title">
                                                {t(
                                                    'welcome.quality.card2.title',
                                                )}
                                            </p>
                                            <p className="text-[11px] text-souk-brown-deep/70">
                                                Fès · Marrakech · Chefchaouen
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </Reveal>
                        </div>
                    </div>
                </section>

                {/* ─────────────────── Craft marquee ─────────────────── */}
                <section
                    aria-hidden="true"
                    className="overflow-hidden border-y border-souk-brown/10 bg-souk-paper py-6"
                >
                    <div className="flex w-max motion-safe:animate-marquee">
                        {[0, 1].map((copy) => (
                            <ul key={copy} className="flex items-center">
                                {MARQUEE.map((word) => (
                                    <li
                                        key={`${copy}-${word}`}
                                        className="flex items-center gap-10 px-10"
                                    >
                                        <span className="font-display text-2xl whitespace-nowrap text-souk-brown/45">
                                            {word}
                                        </span>
                                        <span className="h-1.5 w-1.5 rounded-full bg-souk-brown/25" />
                                    </li>
                                ))}
                            </ul>
                        ))}
                    </div>
                </section>

                {/* ───────────────────── Quality ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-peach py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.13]"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '420px',
                        }}
                    />

                    <div className="relative container">
                        <div className="max-w-3xl">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('welcome.quality.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2.25rem,5vw,4.5rem)] leading-[1.02] tracking-[-0.02em] text-souk-title">
                                    {t('welcome.quality.heading')}
                                </h2>
                            </Reveal>
                            <Reveal delay={160}>
                                <p className="mt-6 max-w-xl text-lg leading-relaxed text-souk-brown-deep/80">
                                    {t('welcome.quality.lead')}
                                </p>
                            </Reveal>
                        </div>

                        <div className="mt-20 grid gap-8 md:grid-cols-3">
                            {[1, 2, 3].map((n, i) => (
                                <Reveal key={n} delay={i * 120}>
                                    <article className="group h-full rounded-3xl border border-souk-brown/12 bg-souk-paper/80 p-9 text-center backdrop-blur-sm transition-all duration-500 hover:-translate-y-2 hover:border-souk-brown/25 hover:shadow-2xl hover:shadow-souk-brown/10">
                                        <VaseIcon className="mx-auto h-[81px] w-[81px] text-souk-brown transition-transform duration-500 group-hover:scale-110" />
                                        <h3 className="mt-7 font-display text-2xl leading-snug text-souk-title">
                                            {t(
                                                `welcome.quality.card${n}.title`,
                                            )}
                                        </h3>
                                        <p className="mt-4 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`welcome.quality.card${n}.body`)}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Harmony ───────────────────── */}
                <section className="bg-souk-paper py-24 lg:py-32">
                    <div className="container grid items-center gap-16 lg:grid-cols-2">
                        <div>
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('welcome.harmony.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2.25rem,5vw,4.5rem)] leading-[1.02] tracking-[-0.02em] text-souk-title">
                                    {t('welcome.harmony.heading')}
                                </h2>
                            </Reveal>

                            <div className="mt-10 space-y-7">
                                {[1, 2, 3].map((n, i) => (
                                    <Reveal
                                        key={n}
                                        delay={140 + i * 110}
                                        from="left"
                                    >
                                        <p
                                            className="border-l-2 border-souk-brown/25 pl-6 text-lg leading-relaxed text-souk-brown"
                                            style={{
                                                marginInlineStart: i * 18,
                                            }}
                                        >
                                            {t(`welcome.harmony.line${n}`)}
                                        </p>
                                    </Reveal>
                                ))}
                            </div>
                        </div>

                        {/* Overlapping image pair, as composed in the design */}
                        <Reveal from="right" delay={120}>
                            <div className="relative mx-auto aspect-[4/5] w-full max-w-[30rem]">
                                <div className="absolute top-0 left-0 h-[78%] w-[74%] overflow-hidden rounded-3xl shadow-2xl shadow-souk-brown/20">
                                    <img
                                        src={leather}
                                        alt="Hand-tooled Moroccan leather goods drying in a workshop"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <div className="absolute right-0 bottom-0 h-[62%] w-[54%] overflow-hidden rounded-3xl border-[10px] border-souk-paper shadow-2xl shadow-souk-brown/25">
                                    <img
                                        src={pottery}
                                        alt="Glazed Moroccan pottery arranged on a shelf"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ──────────────── B2B / B2C — the Hirfaty model ──────────────── */}
                <section className="bg-souk-brown py-24 text-souk-cream lg:py-32">
                    <div className="container">
                        <div className="mx-auto max-w-3xl text-center">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-cream/70 uppercase">
                                    {t('welcome.audience.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2.25rem,5vw,4.25rem)] leading-[1.04] tracking-[-0.02em] text-white">
                                    {t('welcome.audience.heading')}
                                </h2>
                            </Reveal>
                            <Reveal delay={160}>
                                <p className="mt-6 text-lg leading-relaxed text-souk-cream/80">
                                    {t('welcome.audience.lead')}
                                </p>
                            </Reveal>
                        </div>

                        <div className="mt-16 grid gap-8 md:grid-cols-2">
                            {[
                                { key: 'b2c', Icon: Store },
                                { key: 'b2b', Icon: Building2 },
                            ].map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 140}>
                                    <article className="group h-full rounded-3xl border border-souk-cream/20 bg-souk-cream/[0.06] p-10 backdrop-blur transition-all duration-500 hover:-translate-y-2 hover:border-souk-cream/40 hover:bg-souk-cream/10">
                                        <div className="flex items-center gap-4">
                                            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-souk-cream/15 text-souk-cream transition-transform duration-500 group-hover:scale-110">
                                                <Icon
                                                    className="h-7 w-7"
                                                    strokeWidth={1.5}
                                                />
                                            </span>
                                        </div>

                                        <h3 className="mt-8 font-display text-3xl text-white">
                                            {t(`welcome.audience.${key}.title`)}
                                        </h3>
                                        <p className="mt-4 leading-relaxed text-souk-cream/75">
                                            {t(`welcome.audience.${key}.body`)}
                                        </p>

                                        <ul className="mt-8 space-y-3 border-t border-souk-cream/15 pt-7">
                                            {[1, 2, 3].map((p) => (
                                                <li
                                                    key={p}
                                                    className="flex items-start gap-3 text-sm text-souk-cream/85"
                                                >
                                                    <Check
                                                        className="mt-0.5 h-4 w-4 shrink-0 text-souk-cream"
                                                        strokeWidth={2}
                                                    />
                                                    {t(
                                                        `welcome.audience.${key}.point${p}`,
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </article>
                                </Reveal>
                            ))}
                        </div>

                        <Reveal delay={220}>
                            <div className="mt-14 text-center">
                                <Link
                                    href="/seller/onboarding"
                                    className="group inline-flex items-center gap-3 rounded-xl bg-souk-cream px-9 py-4 font-display text-lg text-souk-brown-deep transition-transform duration-300 hover:-translate-y-0.5"
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

                {/* ───────────────── Explore our products ───────────────── */}
                <section className="bg-souk-peach py-24 lg:py-32">
                    <div className="container">
                        <div className="flex flex-wrap items-end justify-between gap-6">
                            <div className="max-w-2xl">
                                <Reveal>
                                    <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                        {t('welcome.products.eyebrow')}
                                    </p>
                                </Reveal>
                                <Reveal delay={80}>
                                    <h2 className="mt-4 font-display text-[clamp(2.25rem,5vw,4.5rem)] leading-[1.02] tracking-[-0.02em] text-souk-title">
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
                                        className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </Reveal>
                        </div>

                        {featuredProducts.length > 0 ? (
                            <div className="mt-16 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                                {featuredProducts.map((product, i) => (
                                    <Reveal
                                        key={product.id}
                                        delay={(i % 3) * 120}
                                    >
                                        <Link
                                            href={`/products/${product.slug}`}
                                            className="group block"
                                        >
                                            {/* arch-topped card, per the design */}
                                            <div className="relative aspect-square overflow-hidden rounded-t-full bg-souk-brown/5">
                                                {product.images?.[0]?.url ? (
                                                    <img
                                                        src={
                                                            product.images[0]
                                                                .url
                                                        }
                                                        alt={product.name}
                                                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center">
                                                        <VaseIcon className="h-20 w-20 text-souk-brown/25" />
                                                    </div>
                                                )}
                                                <div
                                                    aria-hidden="true"
                                                    className="absolute inset-0 rounded-t-full ring-1 ring-souk-brown/10 ring-inset"
                                                />
                                            </div>

                                            <div className="mt-6">
                                                {product.store.city_name && (
                                                    <p className="text-[11px] tracking-[0.18em] text-souk-brown/70 uppercase">
                                                        {
                                                            product.store
                                                                .city_name
                                                        }
                                                    </p>
                                                )}
                                                <h3 className="mt-2 font-display text-xl leading-snug text-souk-title transition-colors group-hover:text-souk-brown">
                                                    {product.name}
                                                </h3>
                                                <div className="mt-2 flex items-baseline gap-3">
                                                    <span className="font-display text-lg text-souk-brown">
                                                        {price(product)}{' '}
                                                        <span className="text-xs">
                                                            {t(
                                                                'welcome.products.currency',
                                                            )}
                                                        </span>
                                                    </span>
                                                    {product.sale_price && (
                                                        <span className="text-sm text-souk-brown-deep/45 line-through">
                                                            {Number(
                                                                product.base_price,
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                                {product.store.store_name && (
                                                    <p className="mt-1.5 text-sm text-souk-brown-deep/60">
                                                        {
                                                            product.store
                                                                .store_name
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                        </Link>
                                    </Reveal>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-16">
                                <div className="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                                    {SHOWCASE.slice(0, 3).map((src, i) => (
                                        <Reveal key={src} delay={i * 120}>
                                            <div className="relative aspect-square overflow-hidden rounded-t-full bg-souk-brown/5">
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

                {/* ───────────────────── Gallery ───────────────────── */}
                <section className="bg-souk-paper py-24 lg:py-32">
                    <div className="container text-center">
                        <Reveal>
                            <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                {t('welcome.gallery.eyebrow')}
                            </p>
                        </Reveal>
                        <Reveal delay={80}>
                            <h2 className="mt-4 font-display text-[clamp(2.5rem,6vw,5.5rem)] leading-none tracking-[-0.02em] text-souk-title">
                                {t('welcome.gallery.heading')}
                            </h2>
                        </Reveal>
                        <Reveal delay={140}>
                            <p className="mx-auto mt-5 max-w-xl text-lg text-souk-brown-deep/75">
                                {t('welcome.gallery.lead')}
                            </p>
                        </Reveal>
                    </div>

                    {/* uneven widths mirror the design's gallery strip */}
                    <div className="mt-16 grid grid-cols-2 gap-3 px-3 md:grid-cols-10 md:gap-4 md:px-4">
                        {[
                            {
                                src: jewelry,
                                span: 'md:col-span-2',
                                alt: 'Silver Moroccan jewellery',
                            },
                            {
                                src: wood,
                                span: 'md:col-span-3',
                                alt: 'Carved cedar wood detail',
                            },
                            {
                                src: pottery,
                                span: 'md:col-span-2',
                                alt: 'A potter at the wheel',
                            },
                            {
                                src: textile,
                                span: 'md:col-span-3',
                                alt: 'Handwoven Moroccan textile',
                            },
                            {
                                src: accessories,
                                span: 'md:col-span-4',
                                alt: 'Handmade artisan accessories',
                            },
                            {
                                src: spice,
                                span: 'md:col-span-3',
                                alt: 'Moroccan spices at the souk',
                            },
                            {
                                src: leather,
                                span: 'md:col-span-3',
                                alt: 'Tooled leather in the tannery',
                            },
                        ].map(({ src, span, alt }, i) => (
                            <Reveal
                                key={alt}
                                delay={(i % 4) * 90}
                                from="scale"
                                className={span}
                            >
                                <div className="group relative aspect-[4/5] overflow-hidden rounded-2xl md:aspect-[3/4]">
                                    <img
                                        src={src}
                                        alt={alt}
                                        className="h-full w-full object-cover transition-transform duration-[900ms] group-hover:scale-110"
                                        loading="lazy"
                                    />
                                    <div
                                        aria-hidden="true"
                                        className="absolute inset-0 bg-souk-brown/0 transition-colors duration-500 group-hover:bg-souk-brown/25"
                                    />
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </section>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
