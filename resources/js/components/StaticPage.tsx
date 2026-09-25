import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import zellij from '@/images/zellij-pattern.jpg';

/**
 * The shell every simple footer page wears: brown zellij hero, then content.
 *
 * Extracted because four of these landed at once (affiliate, rewards,
 * privacy, shipping) and they had no business each re-deriving the same
 * hero — `how-it-works` grew its own copy first and is left alone rather
 * than migrated in passing.
 */
export default function StaticPage({
    title,
    eyebrow,
    heading,
    lead,
    children,
}: {
    /** Browser tab title, already translated. */
    title: string;
    eyebrow: string;
    heading: string;
    lead?: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

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
                                {eyebrow}
                            </p>
                        </Reveal>

                        <Reveal delay={80}>
                            <h1 className="mt-5 max-w-3xl font-display text-[clamp(2.25rem,5.5vw,4rem)] leading-[1.02] font-bold tracking-[-0.02em] text-white">
                                {heading}
                            </h1>
                        </Reveal>

                        {lead && (
                            <Reveal delay={180}>
                                <p className="mt-6 max-w-2xl text-xl leading-relaxed text-souk-cream/85">
                                    {lead}
                                </p>
                            </Reveal>
                        )}
                    </div>
                </section>

                {children}

                <SiteFooter />
            </div>
        </>
    );
}

/**
 * A run of prose — a policy section, or anything that is just a heading and
 * paragraphs. Kept narrow: a legal paragraph at full container width is
 * unreadable.
 */
export function ProseSection({
    heading,
    children,
}: {
    heading: string;
    children: ReactNode;
}) {
    return (
        <section className="border-t border-souk-brown/10 first:border-t-0">
            <div className="container max-w-3xl py-10">
                <h2 className="font-display text-2xl leading-snug text-souk-title">
                    {heading}
                </h2>
                <div className="mt-4 space-y-4 text-[15px] leading-relaxed text-souk-brown-deep/80">
                    {children}
                </div>
            </div>
        </section>
    );
}
