import { Head, Link } from '@inertiajs/react';
import { Compass, PackageCheck, Truck } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import zellij from '@/images/zellij-pattern.jpg';
import { login } from '@/routes';

const STEPS = [
    { key: 'step1', Icon: Compass },
    { key: 'step2', Icon: PackageCheck },
    { key: 'step3', Icon: Truck },
] as const;

/**
 * "How it works" — the footer's buyer-side explainer.
 *
 * Deliberately short: three steps and two ways out (browse, or open a shop).
 * The longer editorial piece about the company lives in `about.tsx`, which is
 * not routed and which the footer no longer links to.
 */
export default function HowItWorks() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('pages.how.title')} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <section className="relative overflow-hidden bg-souk-brown">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.07] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '340px',
                        }}
                    />

                    <div className="relative container py-20 lg:py-24">
                        <Reveal>
                            <p className="text-[11px] tracking-[0.22em] text-souk-cream/70 uppercase rtl:tracking-normal">
                                {t('pages.how.eyebrow')}
                            </p>
                        </Reveal>

                        <Reveal delay={80}>
                            <h1 className="mt-5 max-w-3xl font-display text-[clamp(2.25rem,5.5vw,4rem)] leading-[1.02] font-bold tracking-[-0.02em] text-white">
                                {t('pages.how.heading')}
                            </h1>
                        </Reveal>

                        <Reveal delay={180}>
                            <p className="mt-6 max-w-2xl text-xl leading-relaxed text-souk-cream/85">
                                {t('pages.how.lead')}
                            </p>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── The three steps ───────────────────── */}
                <section className="py-16 lg:py-20">
                    <div className="container">
                        <ol className="grid gap-8 md:grid-cols-3">
                            {STEPS.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={120 + i * 120}>
                                    <li className="h-full rounded-md border border-souk-brown/10 bg-white p-7">
                                        <span
                                            aria-hidden="true"
                                            className="grid size-11 place-items-center rounded-t-full rounded-b-md bg-souk-brown/10 text-souk-brown ring-1 ring-souk-brown/15"
                                        >
                                            <Icon
                                                className="size-[18px]"
                                                strokeWidth={1.6}
                                            />
                                        </span>

                                        <p className="mt-5 text-[11px] font-semibold tracking-[0.18em] text-souk-brown-deep/55 rtl:tracking-normal">
                                            {/* Latin digits in every locale:
                                                the rest of the site numbers
                                                its steps this way. */}
                                            {String(i + 1).padStart(2, '0')}
                                        </p>

                                        <h2 className="mt-1 font-display text-xl text-souk-title">
                                            {t(`pages.how.${key}.title`)}
                                        </h2>
                                        <p className="mt-2.5 text-sm leading-relaxed text-souk-title/70">
                                            {t(`pages.how.${key}.body`)}
                                        </p>
                                    </li>
                                </Reveal>
                            ))}
                        </ol>

                        <Reveal delay={480}>
                            <div className="mt-12 text-center">
                                <Link
                                    href="/products"
                                    className="inline-flex items-center justify-center rounded-xl bg-souk-brown px-7 py-3.5 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep"
                                >
                                    {t('pages.how.buyers.cta')}
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </section>

                {/* ──────────────────── For the artisans ──────────────────── */}
                <section className="pb-16 lg:pb-20">
                    <div className="container">
                        <Reveal from="scale">
                            <div className="rounded-md bg-souk-cream px-6 py-12 text-center sm:px-10">
                                <h2 className="font-display text-2xl text-souk-title">
                                    {t('pages.how.sellers.heading')}
                                </h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-souk-title/70">
                                    {t('pages.how.sellers.body')}
                                </p>
                                <Link
                                    href={login()}
                                    className="mt-7 inline-flex items-center justify-center rounded-xl bg-souk-brown px-7 py-3.5 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep"
                                >
                                    {t('pages.how.sellers.cta')}
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
