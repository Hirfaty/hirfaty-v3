import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgePercent,
    CheckCircle2,
    Clock,
    Layers,
    Send,
    ShieldCheck,
    Store,
    Truck,
} from 'lucide-react';
import type { FormEvent } from 'react';
import ArtisanCta from '@/components/ArtisanCta';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import pottery from '@/images/cat-pottery.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';
import heroMain from '@/images/hero-main.jpg';
import zellij from '@/images/zellij-pattern.png';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.22em] uppercase';

/** Figures for the illustration only — labelled as such on the page. */
const DEMO = { public: 480, trade: 336, minQty: 12 } as const;
const DEMO_SAVING = Math.round((1 - DEMO.trade / DEMO.public) * 100);

const PERKS = [
    { key: 'pricing', Icon: BadgePercent },
    { key: 'catalogue', Icon: Layers },
    { key: 'direct', Icon: Store },
] as const;

/** One photograph per step, so the sequence reads visually as well as in prose. */
const STEPS = [
    { n: 1, src: pottery },
    { n: 2, src: textile },
    { n: 3, src: wood },
] as const;

const NOTES = [
    { key: 'review', Icon: ShieldCheck },
    { key: 'minimum', Icon: Layers },
    { key: 'same', Icon: Truck },
] as const;

/** Anchor the hero button jumps to when there is a form to fill in. */
const FORM_ID = 'request';

type Props = {
    status: string | null;
    canRequest: boolean;
    isProfessional: boolean;
    isVerified: boolean;
    submittedMessage: string | null;
    requestedAt: string | null;
    declineReason: string | null;
};

/**
 * One tile of the price illustration.
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
        <div
            className={`overflow-hidden rounded-3xl border bg-white/70 ${
                trade
                    ? 'border-olive/30 shadow-xl shadow-olive/10'
                    : 'border-souk-brown/12'
            }`}
        >
            <p
                className={`px-5 py-3 text-center ${EYEBROW} ${
                    trade
                        ? 'bg-olive/10 text-olive'
                        : 'bg-souk-brown/5 text-souk-brown-deep/60'
                }`}
            >
                {label}
            </p>

            <div className="relative aspect-[4/3] overflow-hidden bg-souk-brown/5">
                <img
                    src={pottery}
                    alt=""
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
                {trade && (
                    <span className="absolute start-4 top-4 rounded-full bg-olive px-3 py-1 text-[10px] font-medium tracking-[0.16em] text-white uppercase">
                        {t('pro.demo.badge')}
                    </span>
                )}
            </div>

            <div className="p-5">
                <p className={`text-souk-brown-deep/55 ${EYEBROW}`}>
                    {t('pro.demo.workshop')}
                </p>
                <h3 className="mt-2 font-display text-lg leading-snug text-souk-title">
                    {t('pro.demo.product_name')}
                </h3>

                {/* Fixed height so the two cards stay the same size even
                    though only one of them carries a badge row. */}
                <div className="mt-4 min-h-[5.5rem]">
                    {trade ? (
                        <>
                            {/*
                             * The public figure is ruled through rather than
                             * simply replaced: the line draws across it and
                             * the trade price lifts in behind, so the eye
                             * follows the substitution happening.
                             * `origin-left` flips under RTL, or the rule would
                             * draw away from the text instead of across it.
                             */}
                            <span className="relative inline-block text-souk-brown-deep/45">
                                {money(DEMO.public)} MAD
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-x-0 top-1/2 h-px origin-left bg-souk-brown-deep/45 motion-safe:animate-rule-through rtl:origin-right"
                                />
                            </span>

                            <div className="motion-safe:animate-lift-in">
                                <p className="mt-1.5 font-display text-3xl leading-none text-olive">
                                    {money(DEMO.trade)}
                                    <span className="ms-1.5 text-sm">MAD</span>
                                </p>
                                <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-olive px-2 py-0.5 text-[11px] font-medium text-white">
                                        {t('pro.demo.saving').replace(
                                            ':percent',
                                            String(DEMO_SAVING),
                                        )}
                                    </span>
                                    <span className="text-[11px] text-olive/80">
                                        {t('pro.demo.min_note').replace(
                                            ':count',
                                            String(DEMO.minQty),
                                        )}
                                    </span>
                                </div>
                            </div>
                        </>
                    ) : (
                        <p className="font-display text-3xl leading-none text-souk-title">
                            {money(DEMO.public)}
                            <span className="ms-1.5 text-sm">MAD</span>
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function ProfessionalAccount({
    status,
    canRequest,
    isVerified,
    submittedMessage,
    declineReason,
}: Props) {
    const { t, locale } = useTranslation();

    /*
     * The page is public now, so "no user" is a routine state rather than an
     * edge case — `canRequest` is false both for a guest and for a signed-in
     * account that may not ask, and only the shared `auth` prop tells the two
     * apart. A guest is offered registration; an eligible buyer is offered
     * the form further down.
     */
    const { auth } = usePage().props as unknown as {
        auth: { user: { id: number } | null };
    };
    const isGuest = !auth?.user;

    const { data, setData, post, processing, errors } = useForm({
        message: '',
    });

    const money = (v: number) =>
        new Intl.NumberFormat(
            { fr: 'fr-MA', ar: 'ar-MA', en: 'en-MA' }[locale as string] ??
                'fr-MA',
            { maximumFractionDigits: 0 },
        ).format(v);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('professional.request'), { preserveScroll: true });
    };

    /**
     * The headline action, which differs by where the reader stands.
     *
     * The two settled states render as a status rather than a link: a button
     * offering something already granted, or already pending, would be a dead
     * control.
     */
    const cta = (() => {
        if (isVerified) {
            return {
                label: t('pro.hero.cta_verified'),
                href: null,
                Icon: CheckCircle2,
            };
        }

        if (status === 'pending') {
            return {
                label: t('pro.hero.cta_pending'),
                href: null,
                Icon: Clock,
            };
        }

        if (isGuest) {
            return {
                label: t('pro.hero.cta_guest'),
                href: route('register'),
                Icon: ArrowRight,
            };
        }

        // Signed in and allowed to ask — jump to the form at the foot.
        return canRequest
            ? {
                  label: t('pro.hero.cta_buyer'),
                  href: `#${FORM_ID}`,
                  Icon: ArrowRight,
              }
            : { label: null, href: null, Icon: ArrowRight };
    })();

    return (
        <>
            <Head title={t('pro.meta.title')} />

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
                                    <p
                                        className={`text-souk-cream/70 ${EYEBROW}`}
                                    >
                                        {t('pro.hero.eyebrow')}
                                    </p>
                                </Reveal>

                                <h1 className="mt-6 font-display text-[clamp(2.5rem,6vw,5rem)] leading-[0.98] tracking-[-0.02em] text-white">
                                    <Reveal delay={80}>
                                        <span className="block">
                                            {t('pro.hero.title1')}
                                        </span>
                                    </Reveal>
                                    <Reveal delay={180}>
                                        <span className="block text-souk-cream italic">
                                            {t('pro.hero.title2')}
                                        </span>
                                    </Reveal>
                                </h1>

                                <Reveal delay={280}>
                                    <p className="mt-7 max-w-xl text-lg leading-relaxed text-souk-cream/85">
                                        {t('pro.hero.lead')}
                                    </p>
                                </Reveal>

                                {/* A refusal is the one state that owes the
                                    reader a reason, so it is surfaced here
                                    rather than buried further down. */}
                                {status === 'declined' && declineReason && (
                                    <Reveal delay={320}>
                                        <div className="mt-6 max-w-xl rounded-2xl border border-souk-cream/25 bg-souk-cream/10 px-5 py-4">
                                            <p
                                                className={`text-souk-cream/70 ${EYEBROW}`}
                                            >
                                                {t(
                                                    'buyer.professional.declined_reason',
                                                )}
                                            </p>
                                            <p className="mt-2 text-sm leading-relaxed text-souk-cream/90">
                                                {declineReason}
                                            </p>
                                        </div>
                                    </Reveal>
                                )}

                                <Reveal delay={360}>
                                    <div className="mt-10 flex flex-wrap items-center gap-4">
                                        {cta.label &&
                                            (cta.href ? (
                                                <Link
                                                    href={cta.href}
                                                    className="group relative inline-flex items-center gap-3 overflow-hidden rounded-xl bg-souk-cream px-8 py-4 font-display text-lg text-souk-brown-deep shadow-lg shadow-black/10 transition-transform duration-300 hover:-translate-y-0.5"
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className="absolute inset-y-0 -left-1/3 w-1/3 bg-white/50 opacity-0 group-hover:animate-sheen group-hover:opacity-100"
                                                    />
                                                    {cta.label}
                                                    <cta.Icon
                                                        className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
                                                        strokeWidth={1.75}
                                                    />
                                                </Link>
                                            ) : (
                                                <p className="inline-flex items-center gap-3 rounded-xl border border-souk-cream/35 bg-souk-cream/10 px-6 py-3.5 text-souk-cream">
                                                    <cta.Icon
                                                        className="h-5 w-5 shrink-0"
                                                        strokeWidth={1.6}
                                                    />
                                                    {cta.label}
                                                </p>
                                            ))}

                                        <Link
                                            href={route('products.index')}
                                            className="inline-flex items-center gap-2 rounded-xl border border-souk-cream/40 px-7 py-4 text-souk-cream transition-colors duration-300 hover:bg-souk-cream/10"
                                        >
                                            {t('pro.hero.browse')}
                                        </Link>
                                    </div>
                                </Reveal>
                            </div>

                            {/* Arch-framed photograph, drifting and slowly
                                zooming — the treatment the home hero uses, so
                                the two pages read as one site. */}
                            <Reveal from="scale" delay={200}>
                                <div className="relative mx-auto w-full max-w-[26rem]">
                                    <div
                                        aria-hidden="true"
                                        className="absolute -top-6 -right-5 h-full w-full rounded-t-full rounded-bl-3xl border-[3px] border-souk-cream/60"
                                    />
                                    <div className="relative aspect-[530/620] w-full overflow-hidden rounded-t-full rounded-bl-3xl shadow-2xl shadow-black/25 motion-safe:animate-float-slow">
                                        <img
                                            src={heroMain}
                                            alt=""
                                            loading="eager"
                                            className="h-full w-full object-cover motion-safe:animate-ken-burns"
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

                {/* ───────────────── The price illustration ───────────────── */}
                <section className="relative overflow-hidden bg-souk-paper py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-tadelakt motif-fade [--pattern-size:34px]"
                    />

                    <div className="relative container">
                        <div className="max-w-2xl">
                            <Reveal>
                                <p className={`text-souk-brown ${EYEBROW}`}>
                                    {t('pro.demo.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-[1.04] tracking-[-0.02em] text-souk-title">
                                    {t('pro.demo.heading')}
                                </h2>
                            </Reveal>
                            <Reveal delay={140}>
                                <p className="mt-5 text-lg leading-relaxed text-souk-brown-deep/75">
                                    {t('pro.demo.lead')}
                                </p>
                            </Reveal>
                        </div>

                        <div className="mt-14 grid max-w-3xl gap-6 sm:grid-cols-2">
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
                            <p className="mt-6 text-xs text-souk-brown-deep/50 italic">
                                {t('pro.demo.disclaimer')}
                            </p>
                        </Reveal>
                    </div>
                </section>

                {/* ───────────────────── What you get ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-peach py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-arches motif-fade [--pattern-color:rgba(130,71,42,0.13)] [--pattern-size:52px]"
                    />

                    <div className="relative container">
                        <div className="max-w-2xl">
                            <Reveal>
                                <p className={`text-souk-brown ${EYEBROW}`}>
                                    {t('pro.perks.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-[1.04] tracking-[-0.02em] text-souk-title">
                                    {t('pro.perks.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <div className="mt-16 grid gap-6 md:grid-cols-3">
                            {PERKS.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 120}>
                                    <article className="group h-full rounded-3xl border border-souk-brown/12 bg-souk-paper/70 p-9 transition-all duration-500 hover:-translate-y-1.5 hover:border-souk-brown/25 hover:shadow-xl hover:shadow-souk-brown/10">
                                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-souk-brown/8 text-souk-brown ring-1 ring-souk-brown/10 transition-all duration-500 group-hover:rotate-6 group-hover:bg-souk-brown group-hover:text-souk-cream">
                                            <Icon
                                                className="h-7 w-7"
                                                strokeWidth={1.5}
                                            />
                                        </span>
                                        <h3 className="mt-6 font-display text-2xl leading-snug text-souk-title">
                                            {t(`pro.perks.${key}.title`)}
                                        </h3>
                                        <p className="mt-3.5 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`pro.perks.${key}.body`)}
                                        </p>
                                    </article>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ───────────────────── How it works ───────────────────── */}
                <section className="relative overflow-hidden bg-souk-paper py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-weave motif-fade opacity-50 [--pattern-color:rgba(130,71,42,0.07)] [--pattern-size:46px]"
                    />

                    <div className="relative container">
                        <div className="max-w-2xl">
                            <Reveal>
                                <p className={`text-souk-brown ${EYEBROW}`}>
                                    {t('pro.steps.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-[1.04] tracking-[-0.02em] text-souk-title">
                                    {t('pro.steps.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <ol className="mt-16 grid gap-10 md:grid-cols-3 md:gap-8">
                            {STEPS.map(({ n, src }, i) => (
                                <Reveal key={n} delay={i * 130} from="scale">
                                    <li className="group">
                                        <div className="relative aspect-[5/3] overflow-hidden rounded-t-[4rem] rounded-b-2xl bg-souk-brown/5 ring-1 ring-souk-brown/10">
                                            <img
                                                src={src}
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover transition-transform duration-[900ms] group-hover:scale-105"
                                            />
                                            <span className="absolute start-4 bottom-4 flex h-11 w-11 items-center justify-center rounded-full bg-souk-paper/95 font-display text-xl text-souk-title tabular-nums shadow-lg backdrop-blur">
                                                {n}
                                            </span>
                                        </div>
                                        <h3 className="mt-5 font-display text-xl leading-snug text-souk-title">
                                            {t(`pro.steps.${n}.title`)}
                                        </h3>
                                        <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`pro.steps.${n}.body`)}
                                        </p>
                                    </li>
                                </Reveal>
                            ))}
                        </ol>
                    </div>
                </section>

                {/* ────────────────────── Good to know ────────────────────── */}
                <section className="relative overflow-hidden bg-souk-peach py-24 lg:py-32">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 motif-zellij motif-fade opacity-60 [--pattern-color:rgba(130,71,42,0.1)] [--pattern-size:58px]"
                    />

                    <div className="relative container">
                        <div className="max-w-2xl">
                            <Reveal>
                                <p className={`text-souk-brown ${EYEBROW}`}>
                                    {t('pro.notes.eyebrow')}
                                </p>
                            </Reveal>
                            <Reveal delay={80}>
                                <h2 className="mt-4 font-display text-[clamp(2rem,4vw,3.5rem)] leading-[1.04] tracking-[-0.02em] text-souk-title">
                                    {t('pro.notes.heading')}
                                </h2>
                            </Reveal>
                        </div>

                        <div className="mt-14 divide-y divide-souk-brown/12 border-y border-souk-brown/12">
                            {NOTES.map(({ key, Icon }, i) => (
                                <Reveal key={key} delay={i * 100}>
                                    <div className="flex flex-col gap-3 py-7 sm:flex-row sm:gap-8">
                                        <div className="flex items-center gap-3 sm:w-72 sm:shrink-0">
                                            <Icon
                                                className="h-5 w-5 shrink-0 text-souk-brown"
                                                strokeWidth={1.5}
                                            />
                                            <h3 className="font-display text-lg leading-snug text-souk-title">
                                                {t(`pro.notes.${key}.title`)}
                                            </h3>
                                        </div>
                                        <p className="text-[15px] leading-relaxed text-souk-brown-deep/75">
                                            {t(`pro.notes.${key}.body`)}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ──────────────────── Ask, or the verdict ──────────────────── */}
                <section
                    id={FORM_ID}
                    className="relative overflow-hidden bg-souk-paper py-24 lg:py-32"
                >
                    <div className="relative container">
                        <div className="mx-auto max-w-2xl">
                            <Reveal>
                                <div className="rounded-3xl border border-souk-brown/12 bg-souk-peach/50 p-8 sm:p-11">
                                    <ZellijStarIcon className="h-8 w-8 text-souk-brown/30" />

                                    {isVerified ? (
                                        <>
                                            <h2 className="mt-5 font-display text-2xl text-souk-title">
                                                {t(
                                                    'buyer.professional.approved_title',
                                                )}
                                            </h2>
                                            <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t(
                                                    'buyer.professional.approved_body',
                                                )}
                                            </p>
                                        </>
                                    ) : status === 'pending' ? (
                                        <>
                                            <h2 className="mt-5 font-display text-2xl text-souk-title">
                                                {t(
                                                    'buyer.professional.pending_title',
                                                )}
                                            </h2>
                                            <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t(
                                                    'buyer.professional.pending_body',
                                                )}
                                            </p>
                                            {/* Their own words back, so they
                                                can see what was sent. */}
                                            {submittedMessage && (
                                                <p className="mt-5 rounded-2xl border border-souk-brown/12 bg-souk-paper/70 px-5 py-4 text-sm leading-relaxed text-souk-brown-deep/80 italic">
                                                    {submittedMessage}
                                                </p>
                                            )}
                                        </>
                                    ) : isGuest ? (
                                        <>
                                            <h2 className="mt-5 font-display text-2xl text-souk-title">
                                                {t('pro.cta.heading')}
                                            </h2>
                                            <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t('pro.cta.body')}
                                            </p>
                                            <Link
                                                href={route('register')}
                                                className="group mt-6 inline-flex items-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep"
                                            >
                                                {t('pro.hero.cta_guest')}
                                                <ArrowRight
                                                    className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1"
                                                    strokeWidth={1.6}
                                                />
                                            </Link>
                                        </>
                                    ) : canRequest ? (
                                        <>
                                            <h2 className="mt-5 font-display text-2xl text-souk-title">
                                                {t('buyer.professional.title')}
                                            </h2>
                                            <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t('buyer.professional.intro')}
                                            </p>

                                            {/* A previous refusal does not bar
                                                a fresh attempt — see
                                                User::canRequestProfessional. */}
                                            {status === 'declined' && (
                                                <p className="mt-4 text-sm text-souk-brown-deep/70">
                                                    {t(
                                                        'buyer.professional.declined_retry',
                                                    )}
                                                </p>
                                            )}

                                            <form
                                                onSubmit={submit}
                                                className="mt-6"
                                            >
                                                <label
                                                    htmlFor="message"
                                                    className={`mb-2 block text-souk-brown-deep/55 ${EYEBROW}`}
                                                >
                                                    {t(
                                                        'buyer.professional.label',
                                                    )}
                                                </label>
                                                <textarea
                                                    id="message"
                                                    rows={5}
                                                    value={data.message}
                                                    onChange={(e) =>
                                                        setData(
                                                            'message',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={t(
                                                        'buyer.onboarding.professional.placeholder',
                                                    )}
                                                    className="w-full rounded-xl border border-souk-brown/20 bg-white/70 px-4 py-3 text-sm text-souk-title transition-colors placeholder:text-souk-brown-deep/40 focus:border-souk-brown focus:outline-none"
                                                />
                                                {errors.message && (
                                                    <p className="mt-1.5 text-xs text-terracotta">
                                                        {errors.message}
                                                    </p>
                                                )}
                                                <p className="mt-2 text-xs text-souk-brown-deep/60">
                                                    {t(
                                                        'buyer.onboarding.professional.hint',
                                                    )}
                                                </p>

                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="mt-5 inline-flex items-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {t(
                                                        'buyer.professional.submit',
                                                    )}
                                                    <Send
                                                        className="h-4 w-4"
                                                        strokeWidth={1.6}
                                                    />
                                                </button>
                                            </form>
                                        </>
                                    ) : (
                                        /* Signed in, but not an account that
                                           can hold trade access — a seller or
                                           an admin reading out of interest. */
                                        <>
                                            <h2 className="mt-5 font-display text-2xl text-souk-title">
                                                {t('pro.cta.heading')}
                                            </h2>
                                            <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                                {t('pro.cta.body')}
                                            </p>
                                        </>
                                    )}
                                </div>
                            </Reveal>
                        </div>
                    </div>
                </section>

                <ArtisanCta />

                <SiteFooter />
            </div>
        </>
    );
}
