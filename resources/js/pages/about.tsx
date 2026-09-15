import { Head } from '@inertiajs/react';
import { Gem, MapPin, Scale, ShieldCheck } from 'lucide-react';
import ArtisanCta from '@/components/ArtisanCta';
import Reveal from '@/components/home/Reveal';
import VaseIcon from '@/components/home/VaseIcon';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import spice from '@/images/cat-spice.jpg';
import wood from '@/images/cat-wood.jpg';
import heroZellige from '@/images/hero-zellige.jpg';
import zellij from '@/images/zellij-pattern.png';

const VALUES = [
    { key: 'authenticity', Icon: ShieldCheck },
    { key: 'fair', Icon: Scale },
    { key: 'craft', Icon: Gem },
    { key: 'community', Icon: MapPin },
] as const;

const STEPS = [1, 2, 3] as const;

export default function About() {
    const { t } = useTranslation();

    return (
        <>
            <Head title="Hirfaty — Our story" />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <section className="relative overflow-hidden">
                    <div className="relative bg-souk-brown lg:rounded-br-[26rem]">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 opacity-[0.07] mix-blend-screen"
                            style={{
                                backgroundImage: `url(${zellij})`,
                                backgroundSize: '340px',
                            }}
                        />

                        <div className="relative container grid items-center gap-14 py-20 lg:grid-cols-[1.05fr_0.95fr] lg:py-28">
                            <div>
                                <Reveal>
                                    <p className="text-[11px] tracking-[0.22em] text-souk-cream/70 uppercase">
                                        {t('about.hero.eyebrow')}
                                    </p>
                                </Reveal>

                                <h1 className="mt-6 font-display text-[clamp(2.75rem,7vw,6rem)] leading-[0.96] font-bold tracking-[-0.02em] text-white">
                                    <Reveal delay={80}>
                                        <span className="block">
                                            {t('about.hero.title1')}
                                        </span>
                                    </Reveal>
                                    <Reveal delay={180}>
                                        <span className="block text-souk-cream italic">
                                            {t('about.hero.title2')}
                                        </span>
                                    </Reveal>
                                </h1>

                                <Reveal delay={280}>
                                    <p className="mt-7 max-w-lg text-xl leading-relaxed text-souk-cream/85">
                                        {t('about.hero.lead')}
                                    </p>
                                </Reveal>
                            </div>

                            <Reveal
                                from="scale"
                                delay={160}
                                className="relative"
                            >
                                <div className="relative mx-auto w-full max-w-[26rem]">
                                    <div
                                        aria-hidden="true"
                                        className="absolute -top-5 -right-4 h-full w-full rounded-t-full rounded-bl-3xl border-[3px] border-souk-cream/50"
                                    />
                                    <div className="relative aspect-[4/5] w-full overflow-hidden rounded-t-full rounded-bl-3xl shadow-2xl shadow-black/25">
                                        <img
                                            src={heroZellige}
                                            alt="Detail of a Moroccan zellige tile mosaic"
                                            className="h-full w-full object-cover"
                                            loading="eager"
                                        />
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 bg-gradient-to-t from-souk-brown/45 via-transparent to-transparent"
                                        />
                                    </div>
                                </div>
                            </Reveal>
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Mission ───────────────────── */}
                <section className="bg-souk-paper py-24 lg:py-32">
                    <div className="container">
                        <div className="mx-auto max-w-3xl text-center">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('about.mission.eyebrow')}
                                </p>
                            </Reveal>

                            <div className="mt-8 space-y-7">
                                {[1, 2, 3].map((n, i) => (
                                    <Reveal key={n} delay={100 + i * 110}>
                                        <p className="font-display text-[clamp(1.35rem,2.6vw,1.9rem)] leading-snug text-souk-title">
                                            {t(`about.mission.line${n}`)}
                                        </p>
                                    </Reveal>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Values ───────────────────── */}
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
                        <div className="max-w-2xl">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('about.values.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4.5vw,3.5rem)] leading-[1.05] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('about.values.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <div className="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {VALUES.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 110}>
                                    <article className="group h-full border border-souk-brown/12 bg-souk-paper/80 p-7 backdrop-blur-sm transition-all duration-500 hover:-translate-y-2 hover:border-souk-brown/25 hover:shadow-xl hover:shadow-souk-brown/10">
                                        <Icon
                                            className="h-8 w-8 text-souk-brown transition-transform duration-500 group-hover:scale-110"
                                            strokeWidth={1.5}
                                        />
                                        <h3 className="mt-5 font-display text-xl leading-snug text-souk-title">
                                            {t(`about.values.${key}.title`)}
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/75">
                                            {t(`about.values.${key}.body`)}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────── Workshop to home ───────────────── */}
                <section className="bg-souk-paper py-24 lg:py-32">
                    <div className="container grid items-center gap-16 lg:grid-cols-2">
                        {/* Image left this time — mirrors the home page's
                            harmony section so the two pages don't read as
                            identical templates with swapped copy. */}
                        <Reveal
                            from="left"
                            delay={100}
                            className="order-2 lg:order-1"
                        >
                            <div className="relative mx-auto aspect-[4/5] w-full max-w-[28rem]">
                                <div className="absolute top-0 right-0 h-[76%] w-[72%] overflow-hidden rounded-3xl shadow-2xl shadow-souk-brown/20">
                                    <img
                                        src={wood}
                                        alt="Carved cedar wood in a Moroccan workshop"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                                <div className="absolute bottom-0 left-0 h-[58%] w-[52%] overflow-hidden rounded-3xl border-[10px] border-souk-paper shadow-2xl shadow-souk-brown/25">
                                    <img
                                        src={spice}
                                        alt="Moroccan spices arranged at the souk"
                                        className="h-full w-full object-cover transition-transform duration-700 hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </Reveal>

                        <div className="order-1 lg:order-2">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('about.story.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4.5vw,3.5rem)] leading-[1.05] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('about.story.heading')}
                                </h2>
                            </Reveal>

                            <div className="mt-8 space-y-6">
                                {[1, 2, 3].map((n, i) => (
                                    <Reveal
                                        key={n}
                                        delay={140 + i * 110}
                                        from="right"
                                    >
                                        <p
                                            className="border-s-2 border-souk-brown/25 ps-6 text-xl leading-relaxed text-souk-brown"
                                            style={{
                                                marginInlineStart: i * 14,
                                            }}
                                        >
                                            {t(`about.story.line${n}`)}
                                        </p>
                                    </Reveal>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ───────────────────── How it works ───────────────────── */}
                <section className="bg-souk-brown py-24 text-souk-cream lg:py-32">
                    <div className="container">
                        <div className="mx-auto max-w-2xl text-center">
                            <Reveal>
                                <p className="text-[11px] tracking-[0.22em] text-souk-cream/70 uppercase">
                                    {t('about.how.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4.5vw,3.5rem)] leading-[1.05] font-bold tracking-[-0.02em] text-white">
                                    {t('about.how.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <div className="mt-16 grid gap-10 md:grid-cols-3">
                            {STEPS.map((n, i) => (
                                <Reveal key={n} delay={i * 130}>
                                    <div className="text-center md:text-start">
                                        <span className="font-display text-5xl text-souk-cream/30">
                                            0{n}
                                        </span>
                                        <h3 className="mt-3 font-display text-2xl text-white">
                                            {t(`about.how.step${n}.title`)}
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-souk-cream/75">
                                            {t(`about.how.step${n}.body`)}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Stats ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-peach py-20 lg:py-24">
                    <div className="container">
                        <Reveal>
                            <p className="text-center text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                {t('about.stats.eyebrow')}
                            </p>
                        </Reveal>
                        <Reveal delay={80}>
                            <h2 className="mt-4 text-center font-display text-[clamp(1.75rem,3.5vw,2.75rem)] leading-tight font-bold tracking-[-0.02em] text-souk-title">
                                {t('about.stats.heading')}
                            </h2>
                        </Reveal>

                        <Reveal delay={160}>
                            <div className="mx-auto mt-14 flex max-w-2xl flex-col items-center justify-center gap-10 sm:flex-row sm:gap-16">
                                <VaseIcon className="hidden h-14 w-14 shrink-0 text-souk-brown/40 sm:block" />
                                {[
                                    ['120+', t('about.stats.artisans')],
                                    ['14', t('about.stats.cities')],
                                    ['100%', t('about.stats.handmade')],
                                ].map(([value, label]) => (
                                    <div key={label} className="text-center">
                                        <dt className="font-display text-4xl text-souk-title">
                                            {value}
                                        </dt>
                                        <dd className="mt-1 text-xs tracking-wide text-souk-brown-deep/70">
                                            {label}
                                        </dd>
                                    </div>
                                ))}
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
