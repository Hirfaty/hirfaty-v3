import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Check, Clock, PackagePlus, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';
import { dashboard } from '@/routes/seller';

/**
 * How long the seller gets to read this before being moved along.
 *
 * Long enough to actually take in that a review is pending — the one thing
 * this page exists to say — without stranding someone on a screen that has no
 * decision to make.
 */
const REDIRECT_SECONDS = 8;

interface Props {
    /** Null only if the shop row was deleted by hand — see WelcomeController. */
    storeName: string | null;
    /** False once an admin has cleared the shop; flips the copy to past tense. */
    isPending: boolean;
}

/**
 * The last screen of seller signup: the workshop exists, and it is waiting.
 *
 * `verification_status` is `pending` and `status` is `inactive` until an admin
 * clears it (StoreVerificationService), so nothing the seller adds is publicly
 * readable yet. Saying that here — before they upload a catalogue and wonder
 * why the marketplace never shows it — is the whole job of the page.
 *
 * It forwards itself to the dashboard rather than waiting for a click: there
 * is nothing to decide, only something to be told. The countdown is visible
 * and there is a button that skips it, so the redirect is something the seller
 * can see coming rather than a page that yanks itself away mid-sentence.
 */
export default function SellerWelcome({ storeName, isPending }: Props) {
    const { t } = useTranslation();
    const [secondsLeft, setSecondsLeft] = useState(REDIRECT_SECONDS);

    useEffect(() => {
        // Ticks once a second and navigates on the tick that hits zero, so the
        // visible number and the actual redirect can never disagree.
        const timer = setInterval(() => {
            setSecondsLeft((left) => {
                if (left <= 1) {
                    clearInterval(timer);
                    router.visit(dashboard.url());

                    return 0;
                }

                return left - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    const steps = [
        { key: 'review', Icon: Clock },
        { key: 'add', Icon: PackagePlus },
        { key: 'live', Icon: Sparkles },
    ] as const;

    return (
        <>
            <Head title={t('seller.welcome.eyebrow')} />

            <div className="relative min-h-screen bg-souk-paper">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.04]"
                    style={{
                        backgroundImage: `url(${zellij})`,
                        backgroundSize: '320px',
                    }}
                />

                <div className="relative mx-auto flex min-h-screen max-w-2xl flex-col px-6 py-10 sm:px-8">
                    <Link href="/" className="inline-flex items-center gap-3">
                        <img
                            src={logo}
                            alt=""
                            className="h-8 w-8 object-contain"
                        />
                        <span className="font-display text-xl text-souk-title">
                            Hirfaty
                            <span className="text-terracotta">.</span>
                        </span>
                    </Link>

                    <div className="flex flex-1 flex-col justify-center py-10 motion-safe:animate-fade-up">
                        <span className="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-olive/15">
                            <Check
                                className="h-7 w-7 text-olive"
                                strokeWidth={2.2}
                            />
                        </span>

                        <p className="mt-6 text-[11px] tracking-[0.2em] text-souk-brown/70 uppercase">
                            {t('seller.welcome.eyebrow')}
                        </p>

                        <h1 className="mt-3 font-display text-[clamp(1.7rem,3.2vw,2.6rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                            {t('seller.welcome.title')}
                        </h1>

                        <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-souk-brown-deep/75">
                            {/* An admin may already have cleared the shop by
                                the time this is bookmarked and revisited —
                                telling them to wait for a review that has
                                happened would be wrong. */}
                            {(isPending
                                ? t('seller.welcome.body')
                                : t('seller.welcome.body_approved')
                            ).replace(':name', storeName ?? '—')}
                        </p>

                        <section className="mt-9">
                            <h2 className="text-xs tracking-[0.14em] text-souk-brown-deep/50 uppercase">
                                {t('seller.welcome.steps_title')}
                            </h2>

                            <ol className="mt-5 space-y-5">
                                {steps.map(({ key, Icon }, i) => (
                                    <li
                                        key={key}
                                        className="flex items-start gap-4"
                                    >
                                        <span className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-souk-brown/20 bg-white">
                                            <Icon
                                                className="h-4 w-4 text-souk-brown"
                                                strokeWidth={1.6}
                                            />
                                            <span className="absolute -end-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-souk-brown text-[10px] text-souk-paper">
                                                {i + 1}
                                            </span>
                                        </span>
                                        <p className="pt-1.5 text-sm leading-relaxed text-souk-brown-deep/80">
                                            {t(`seller.welcome.step_${key}`)}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </section>

                        {/* ── The forward ───────────────────────────── */}
                        <div className="mt-9 flex flex-wrap items-center gap-4">
                            <Link
                                href={dashboard()}
                                className="group inline-flex items-center gap-2 rounded-full bg-souk-brown px-7 py-3 text-sm text-souk-paper transition-colors hover:bg-souk-brown-deep"
                            >
                                {t('seller.welcome.to_dashboard')}
                                <ArrowRight
                                    className="h-4 w-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180"
                                    strokeWidth={1.6}
                                />
                            </Link>

                            {/*
                             * `aria-live="polite"` rather than silence: a
                             * screen-reader user is about to be navigated away
                             * and should hear it coming. Polite, not assertive,
                             * so it waits for a gap instead of interrupting the
                             * heading on every tick.
                             */}
                            <p
                                aria-live="polite"
                                className="text-sm text-souk-brown-deep/60"
                            >
                                {t('seller.welcome.redirecting').replace(
                                    ':seconds',
                                    String(secondsLeft),
                                )}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
