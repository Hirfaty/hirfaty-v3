import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Hand, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';

interface OnboardingCategory {
    id: number;
    name: string;
}

interface Props {
    categories: OnboardingCategory[];
    // Null for the fast-signup flow, which no longer asks for a name at all —
    // see RegistrationCodeService::complete. This is the very first screen a
    // brand-new buyer lands on, so it is also the first place that has to
    // cope with not having one yet.
    firstName: string | null;
}

type StepId = 'welcome' | 'interests';

const STEPS: StepId[] = ['welcome', 'interests'];

/**
 * The short intro a new buyer sees once, straight after verifying their
 * email. Two steps — a welcome and a pick of craft interests — in a single
 * centered column.
 *
 * This used to be a split-screen wizard with a rotating artwork panel on the
 * left and a third step asking whether the buyer wanted a trade account.
 * Both are gone: the artwork was decoration competing with a two-minute task,
 * and the trade step asked a business question of someone who had not yet
 * seen the market — a buyer who does need wholesale pricing can still ask for
 * it any time from their account (/professional-account), when the need is
 * actually in front of them rather than guessed at during signup.
 */
export default function Onboarding({ categories, firstName }: Props) {
    const { t } = useTranslation();

    const [stepIndex, setStepIndex] = useState(0);
    const current = STEPS[stepIndex];
    const isLast = stepIndex === STEPS.length - 1;

    const [interests, setInterests] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);

    const toggleInterest = (id: number) =>
        setInterests((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id],
        );

    const finish = () => {
        setProcessing(true);
        router.post(
            route('onboarding.store'),
            { interests },
            { onFinish: () => setProcessing(false) },
        );
    };

    const skip = () => router.post(route('onboarding.skip'));

    return (
        <>
            <Head title={t('buyer.onboarding.title')} />

            <div className="flex min-h-screen flex-col items-center bg-souk-paper px-6 py-8 sm:px-10">
                <div className="flex w-full max-w-lg flex-1 flex-col">
                    <div className="flex items-center gap-2.5">
                        <img
                            src={logo}
                            alt=""
                            className="h-7 w-7 object-contain"
                        />
                        <span className="font-display text-lg text-souk-title">
                            Hirfaty
                            <span className="text-souk-brown">.</span>
                        </span>
                    </div>

                    {/* Progress + skip */}
                    <div className="mt-8 flex items-center justify-between gap-6">
                        <div className="flex items-center gap-2">
                            {STEPS.map((s, i) => (
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
                                <section className="text-center">
                                    <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-terracotta/10">
                                        <Hand
                                            className="h-7 w-7 text-terracotta"
                                            strokeWidth={1.5}
                                        />
                                    </span>

                                    <h1 className="mt-6 font-display text-[clamp(1.8rem,4vw,2.6rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                                        {firstName
                                            ? t(
                                                  'buyer.onboarding.welcome.title',
                                              ).replace(':name', firstName)
                                            : t(
                                                  'buyer.onboarding.welcome.title_generic',
                                              )}
                                    </h1>

                                    <p className="mx-auto mt-4 max-w-md text-[15px] leading-relaxed text-souk-brown-deep/75">
                                        {t('buyer.onboarding.welcome.body')}
                                    </p>

                                    <ul className="mx-auto mt-8 max-w-sm space-y-4 text-left">
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
                                <section className="text-center">
                                    <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-terracotta/10">
                                        <Sparkles
                                            className="h-7 w-7 text-terracotta"
                                            strokeWidth={1.5}
                                        />
                                    </span>

                                    <h2 className="mt-6 font-display text-[clamp(1.6rem,3.4vw,2.1rem)] leading-[1.12] tracking-[-0.02em] text-souk-title">
                                        {t('buyer.onboarding.interests.title')}
                                    </h2>

                                    <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-souk-brown-deep/70">
                                        {t('buyer.onboarding.interests.body')}
                                    </p>

                                    <div className="mt-7 flex flex-wrap justify-center gap-2.5">
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
                            disabled={processing}
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
                </div>
            </div>
        </>
    );
}
