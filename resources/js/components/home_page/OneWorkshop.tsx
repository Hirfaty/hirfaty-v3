import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    CratesIcon,
    ParcelIcon,
    PriceTagIcon,
    RepeatOrderIcon,
    StorefrontIcon,
    WholesaleTagIcon,
} from '@/components/home/CraftIcons';
import { useTranslation } from '@/hooks/use-translation';
import leather from '@/images/cat-leather.jpg';
import pottery from '@/images/cat-pottery.jpg';
import spice from '@/images/cat-spice.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';
import Reveal from '../home/Reveal';

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

export default function OneWorkshop() {
    const { t } = useTranslation();

    return (
        <section className="relative overflow-hidden border-y border-souk-brown/10 bg-souk-paper py-6 lg:py-6">
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
                        <p className="mx-auto mt-6 max-w-xl text-xl leading-relaxed text-souk-brown-deep/75">
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
                <div className="mt-10 grid items-center gap-14 lg:mt-14 lg:grid-cols-[1fr_auto_1fr] lg:gap-0">
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
                                        alt={t('welcome.audience.hub_alt')}
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
                            <p className="mt-7 max-w-[13rem] text-center font-display text-xl leading-snug text-souk-title">
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
                    <div className="mt-10 text-center">
                        <Link
                            href="/seller/onboarding"
                            className="group inline-flex items-center gap-3 rounded-sm bg-souk-brown px-9 py-4 font-display text-lg text-souk-cream transition-colors duration-300 hover:bg-souk-brown-deep"
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
    );
}
