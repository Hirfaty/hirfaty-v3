import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Check,
    Hand,
    Hotel,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import heroMain from '@/images/hero-main.jpg';
import heroTextile from '@/images/hero-textile.jpg';
import heroZellige from '@/images/hero-zellige.jpg';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';

interface OnboardingCategory {
    id: number;
    name: string;
    icon: string | null;
}

interface Props {
    categories: OnboardingCategory[];
    canRequestProfessional: boolean;
    firstName: string;
}

type StepId = 'welcome' | 'interests' | 'professional';

/** Artwork per step, so the panel moves with the story rather than sitting still. */
const STEP_IMAGE: Record<StepId, string> = {
    welcome: heroMain,
    interests: heroTextile,
    professional: heroZellige,
};

export default function Onboarding({
    categories,
    canRequestProfessional,
    firstName,
}: Props) {
    const { t } = useTranslation();

    /*
     * The trade step is dropped entirely for an account that can't ask (an
     * existing professional, or one with a request already pending) rather
     * than shown disabled — a dead step is worse than no step.
     */
    const steps: StepId[] = [
        'welcome',
        'interests',
        ...(canRequestProfessional ? (['professional'] as StepId[]) : []),
    ];

    const [stepIndex, setStepIndex] = useState(0);
    const current = steps[stepIndex];
    const isLast = stepIndex === steps.length - 1;

    const [interests, setInterests] = useState<number[]>([]);
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);

    /*
     * Whether the buyer actually opened the trade form. Tracked separately
     * from the message text so a typed-then-cleared box doesn't read as
     * "asked" — the service files a request off a non-empty message, and a
     * blank one would sit in the admin queue saying nothing.
     */
    const [wantsProfessional, setWantsProfessional] = useState(false);

    const toggleInterest = (id: number) =>
        setInterests((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id],
        );

    const finish = () => {
        setProcessing(true);
        router.post(
            route('onboarding.store'),
            {
                interests,
                professional_message:
                    wantsProfessional && message.trim() !== '' ? message : null,
            },
            { onFinish: () => setProcessing(false) },
        );
    };

    const skip = () => router.post(route('onboarding.skip'));

    const canContinue =
        current !== 'professional' ||
        !wantsProfessional ||
        message.trim().length >= 20;

    return (
        <>
            <Head title={t('buyer.onboarding.title')} />

            <div className="min-h-screen bg-souk-paper lg:grid lg:h-screen lg:grid-cols-[0.85fr_1.15fr]">
                {/* ── Artwork panel (desktop only) ───────────────────── */}
                <aside className="relative hidden overflow-hidden bg-souk-brown lg:flex lg:h-screen lg:flex-col">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.08] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '320px',
                        }}
                    />

                    <div className="relative shrink-0 px-10 pt-10">
                        <span className="inline-flex items-center gap-3">
                            <img
                                src={logo}
                                alt=""
                                className="h-8 w-8 object-contain"
                            />
                            <span className="font-display text-xl text-white">
                                Hirfaty
                                <span className="text-souk-cream">.</span>
                            </span>
                        </span>
                    </div>

                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-10 py-8">
                        <div className="relative h-full max-h-[24rem]">
                            <div
                                aria-hidden="true"
                                className="absolute -top-4 -right-4 h-full w-full rounded-t-full rounded-bl-2xl border-2 border-souk-cream/40"
                            />
                            <div className="relative aspect-[4/5] h-full overflow-hidden rounded-t-full rounded-bl-2xl shadow-2xl shadow-black/30">
                                <img
                                    key={current}
                                    src={STEP_IMAGE[current]}
                                    alt=""
                                    className="h-full w-full object-cover motion-safe:animate-fade-up"
                                />
                                <div
                                    aria-hidden="true"
                                    className="absolute inset-0 bg-gradient-to-t from-souk-brown/50 via-transparent to-transparent"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative shrink-0 px-10 pb-10">
                        <p className="max-w-md font-display text-xl leading-snug text-souk-cream">
                            {t(`buyer.onboarding.${current}.quote`)}
                        </p>
                    </div>
                </aside>

                {/* ── Content panel ─────────────────────────────────── */}
                <main className="flex min-h-screen flex-col px-6 py-8 sm:px-10 lg:h-screen lg:min-h-0 lg:overflow-y-auto lg:px-16 lg:py-10">
                    {/* Progress + skip */}
                    <div className="flex items-center justify-between gap-6">
                        <div className="flex items-center gap-2">
                            {steps.map((s, i) => (
                                <span
                                    key={s}
                                    className={`h-1 rounded-full transition-all duration-300 ${
                                        i <= stepIndex
                                            ? 'w-8 bg-terracotta'
                                            : 'w-4 bg-souk-brown/20'
                                    }`}
                                />
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={skip}
                            className="text-xs text-souk-brown-deep/50 underline-offset-4 transition-colors hover:text-souk-brown hover:underline"
                        >
                            {t('buyer.onboarding.skip')}
                        </button>
                    </div>

                    <div className="flex flex-1 flex-col justify-center py-10">
                        <div
                            key={current}
                            className="motion-safe:animate-fade-up"
                        >
                            {/* ── Step 1: welcome ───────────────────── */}
                            {current === 'welcome' && (
                                <section>
                                    <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-terracotta/10 text-3xl">
                                        <Hand
                                            className="h-7 w-7 text-terracotta"
                                            strokeWidth={1.5}
                                        />
                                    </span>

                                    <h1 className="mt-6 font-display text-[clamp(2rem,3.6vw,3rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                                        {t(
                                            'buyer.onboarding.welcome.title',
                                        ).replace(':name', firstName)}
                                    </h1>

                                    <p className="mt-4 max-w-lg text-[15px] leading-relaxed text-souk-brown-deep/75">
                                        {t('buyer.onboarding.welcome.body')}
                                    </p>

                                    <ul className="mt-8 space-y-4">
                                        {[
                                            'direct',
                                            'authentic',
                                            'delivery',
                                        ].map((k) => (
                                            <li
                                                key={k}
                                                className="flex items-start gap-3"
                                            >
                                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-olive/15">
                                                    <Check
                                                        className="h-3.5 w-3.5 text-olive"
                                                        strokeWidth={2.5}
                                                    />
                                                </span>
                                                <span className="text-sm leading-relaxed text-souk-brown-deep/80">
                                                    {t(
                                                        `buyer.onboarding.welcome.point_${k}`,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}

                            {/* ── Step 2: interests ─────────────────── */}
                            {current === 'interests' && (
                                <section>
                                    <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-terracotta/10">
                                        <Sparkles
                                            className="h-7 w-7 text-terracotta"
                                            strokeWidth={1.5}
                                        />
                                    </span>

                                    <h2 className="mt-6 font-display text-[clamp(1.7rem,3vw,2.4rem)] leading-[1.12] tracking-[-0.02em] text-souk-title">
                                        {t('buyer.onboarding.interests.title')}
                                    </h2>

                                    <p className="mt-3 max-w-lg text-[15px] leading-relaxed text-souk-brown-deep/70">
                                        {t('buyer.onboarding.interests.body')}
                                    </p>

                                    <div className="mt-7 flex flex-wrap gap-2.5">
                                        {categories.map((c) => {
                                            const active = interests.includes(
                                                c.id,
                                            );

                                            return (
                                                <button
                                                    key={c.id}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleInterest(c.id)
                                                    }
                                                    aria-pressed={active}
                                                    className={`inline-flex items-center gap-2 rounded-full border px-4 py-2.5 text-sm transition-all ${
                                                        active
                                                            ? 'border-terracotta bg-terracotta text-souk-paper shadow-sm'
                                                            : 'border-souk-brown/20 text-souk-brown-deep/75 hover:border-souk-brown/45 hover:bg-souk-brown/5'
                                                    }`}
                                                >
                                                    {c.icon && (
                                                        <span aria-hidden="true">
                                                            {c.icon}
                                                        </span>
                                                    )}
                                                    {c.name}
                                                    {active && (
                                                        <Check
                                                            className="h-3.5 w-3.5"
                                                            strokeWidth={2.5}
                                                        />
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>

                                    <p className="mt-5 text-xs text-souk-brown-deep/45">
                                        {t(
                                            'buyer.onboarding.interests.optional',
                                        )}
                                    </p>
                                </section>
                            )}

                            {/* ── Step 3: professional ──────────────── */}
                            {current === 'professional' && (
                                <section>
                                    <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-terracotta/10">
                                        <Hotel
                                            className="h-7 w-7 text-terracotta"
                                            strokeWidth={1.5}
                                        />
                                    </span>

                                    <h2 className="mt-6 font-display text-[clamp(1.7rem,3vw,2.4rem)] leading-[1.12] tracking-[-0.02em] text-souk-title">
                                        {t(
                                            'buyer.onboarding.professional.title',
                                        )}
                                    </h2>

                                    <p className="mt-3 max-w-lg text-[15px] leading-relaxed text-souk-brown-deep/70">
                                        {t(
                                            'buyer.onboarding.professional.body',
                                        )}
                                    </p>

                                    <div className="mt-6 rounded-2xl border border-souk-brown/15 bg-souk-cream/40 p-5">
                                        <div className="flex items-start gap-3">
                                            <BadgeCheck
                                                className="mt-0.5 h-5 w-5 shrink-0 text-olive"
                                                strokeWidth={1.6}
                                            />
                                            <p className="text-sm leading-relaxed text-souk-brown-deep/80">
                                                {t(
                                                    'buyer.onboarding.professional.perk',
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    {!wantsProfessional ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setWantsProfessional(true)
                                            }
                                            className="mt-6 inline-flex items-center gap-2 rounded-full border border-terracotta px-5 py-2.5 text-sm text-terracotta transition-colors hover:bg-terracotta hover:text-souk-paper"
                                        >
                                            {t(
                                                'buyer.onboarding.professional.cta',
                                            )}
                                            <ArrowRight
                                                className="h-4 w-4 rtl:rotate-180"
                                                strokeWidth={1.6}
                                            />
                                        </button>
                                    ) : (
                                        <div className="mt-6">
                                            <label
                                                htmlFor="professional_message"
                                                className="mb-1.5 block text-xs text-souk-brown-deep/60"
                                            >
                                                {t(
                                                    'buyer.onboarding.professional.label',
                                                )}
                                            </label>
                                            <textarea
                                                id="professional_message"
                                                rows={5}
                                                autoFocus
                                                value={message}
                                                onChange={(e) =>
                                                    setMessage(e.target.value)
                                                }
                                                maxLength={1500}
                                                placeholder={t(
                                                    'buyer.onboarding.professional.placeholder',
                                                )}
                                                className="w-full rounded-xl border border-souk-brown/25 bg-white px-4 py-3 text-sm leading-relaxed text-souk-title placeholder:text-souk-brown-deep/30 focus:border-souk-brown focus:ring-2 focus:ring-souk-brown/10 focus:outline-none"
                                            />
                                            <p className="mt-1.5 text-[11px] text-souk-brown-deep/45">
                                                {t(
                                                    'buyer.onboarding.professional.hint',
                                                )}
                                            </p>
                                        </div>
                                    )}
                                </section>
                            )}
                        </div>
                    </div>

                    {/* ── Nav ───────────────────────────────────────── */}
                    <div className="flex items-center justify-between gap-4 border-t border-souk-brown/12 pt-6">
                        {stepIndex > 0 ? (
                            <button
                                type="button"
                                onClick={() => setStepIndex((i) => i - 1)}
                                className="group inline-flex items-center gap-2 text-sm text-souk-brown-deep/65 transition-colors hover:text-souk-brown"
                            >
                                <ArrowLeft
                                    className="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180"
                                    strokeWidth={1.5}
                                />
                                {t('buyer.onboarding.back')}
                            </button>
                        ) : (
                            <span />
                        )}

                        <button
                            type="button"
                            disabled={processing || !canContinue}
                            onClick={() =>
                                isLast ? finish() : setStepIndex((i) => i + 1)
                            }
                            className="group inline-flex items-center gap-2 rounded-full bg-souk-brown px-7 py-3 text-sm text-souk-paper transition-colors hover:bg-souk-brown-deep disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {isLast
                                ? t('buyer.onboarding.finish')
                                : t('buyer.onboarding.next')}
                            <ArrowRight
                                className="h-4 w-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180"
                                strokeWidth={1.6}
                            />
                        </button>
                    </div>
                </main>
            </div>
        </>
    );
}
