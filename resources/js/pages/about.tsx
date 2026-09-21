import { Head } from '@inertiajs/react';
import { useRef } from 'react';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import ceoPhoto from '@/images/about/about-ceo-pic.jpeg';
import riadBackground from '@/images/hero/hero-1.jpeg';

/**
 * Whole page is one full-bleed section: the riad photograph behind
 * everything, the founder's portrait sitting on top of it, and the bio in
 * its own opaque card so the paragraph stays legible over the busy
 * courtyard tiling underneath it.
 *
 * `ceoPhoto` is a placeholder silhouette (see the SVG itself) — drop the
 * real portrait in at `resources/js/images/about/ceo-placeholder.svg`, or
 * point this import at a new file, to swap it in.
 */
const STORY_POINTS = ['vision', 'gesture', 'commitment'] as const;

const IMPACT_POINTS = ['support', 'knowledge', 'community'] as const;

export default function About() {
    const { t } = useTranslation();

    /* The hero keeps the overlay bar transparent until its photograph has
       scrolled out of the viewport. */
    const heroRef = useRef<HTMLElement>(null);

    return (
        <>
            <Head title="Hirfaty — Our story" />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader variant="overlay" overlayAnchor={heroRef} />

                <section
                    ref={heroRef}
                    className="relative isolate overflow-hidden"
                >
                    <img
                        src={riadBackground}
                        alt="A traditional Moroccan riad courtyard"
                        className="absolute inset-0 -z-10 h-full w-full object-cover"
                    />
                    {/* Darkens the photograph so both the portrait's edge and
                        the bio card read clearly against it. */}
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-10 bg-souk-title/55"
                    />

                    <div className="container flex flex-col items-center gap-10 py-24 lg:py-32">
                        <Reveal from="scale">
                            <div className="relative mx-auto overflow-hidden rounded-md shadow-2xl shadow-black/40 sm:h-96 sm:min-w-72">
                                <img
                                    src={ceoPhoto}
                                    alt={t('about.founder.name')}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                        </Reveal>

                        <Reveal delay={120} className="w-full max-w-2xl">
                            <div className="rounded-3xl bg-souk-paper/95 p-8 text-center shadow-2xl shadow-black/30 backdrop-blur-sm sm:p-12">
                                <p className="text-[11px] tracking-[0.22em] text-souk-brown uppercase">
                                    {t('about.founder.eyebrow')}
                                </p>
                                <h1 className="mt-4 font-display text-[clamp(1.9rem,4vw,2.75rem)] leading-[1.05] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('about.founder.name')}
                                </h1>
                                <p className="mt-1.5 text-sm tracking-wide text-souk-brown-deep/70 uppercase">
                                    {t('about.founder.role')}
                                </p>
                                <p className="mt-6 text-lg leading-relaxed text-souk-brown-deep">
                                    {t('about.founder.quote')}
                                </p>
                                <p className="mt-6 border-t border-souk-brown/12 pt-6 text-base leading-relaxed text-souk-brown-deep/85 italic">
                                    {t('about.founder.portrait')}
                                </p>
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── Story ───────────────────── */}
                <section className="bg-souk-paper py-24 lg:py-32">
                    <div className="container">
                        <Reveal>
                            <p className="mx-auto max-w-3xl text-center text-xl leading-relaxed text-souk-brown-deep sm:text-2xl">
                                {t('about.story.intro')}
                            </p>
                        </Reveal>

                        <div className="mt-16 grid gap-10 sm:grid-cols-3 sm:gap-8">
                            {STORY_POINTS.map((key, i) => (
                                <Reveal key={key} delay={i * 110}>
                                    <div className="text-center sm:text-start">
                                        <h3 className="font-display text-xl leading-snug text-souk-title">
                                            {t(`about.story.${key}.title`)}
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/75">
                                            {t(`about.story.${key}.body`)}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────────── Impact ───────────────────── */}
                <section className="bg-souk-peach py-24 lg:py-32">
                    <div className="container">
                        <div className="mx-auto max-w-2xl text-center">
                            <Reveal>
                                <h2 className="font-display text-[clamp(2rem,4.5vw,3.5rem)] leading-[1.05] font-bold tracking-[-0.02em] text-souk-title">
                                    {t('about.impact.heading')}
                                </h2>
                            </Reveal>
                            <Reveal delay={80}>
                                <p className="mt-6 text-lg leading-relaxed text-souk-brown-deep">
                                    {t('about.impact.intro')}
                                </p>
                            </Reveal>
                        </div>

                        <div className="mt-16 grid gap-6 sm:grid-cols-3">
                            {IMPACT_POINTS.map((key, i) => (
                                <Reveal key={key} delay={i * 110}>
                                    <article className="h-full border border-souk-brown/12 bg-souk-paper/80 p-7">
                                        <h3 className="font-display text-lg leading-snug text-souk-title">
                                            {t(`about.impact.${key}.title`)}
                                        </h3>
                                        <p className="mt-3 text-sm leading-relaxed text-souk-brown-deep/75">
                                            {t(`about.impact.${key}.body`)}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>

                        <Reveal delay={140}>
                            <p className="mx-auto mt-14 max-w-2xl text-center font-display text-xl leading-relaxed text-souk-title">
                                {t('about.impact.closing')}
                            </p>
                        </Reveal>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
