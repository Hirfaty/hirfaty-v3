import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check, Clock, PackagePlus, Sparkles } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import zellij from '@/images/zellij-pattern.png';

interface Props {
    storeName: string;
    /** Drives whether the "add a first product" nudge is worth showing. */
    hasProducts: boolean;
    /** Craft, city or description still missing — see StoreSetupController. */
    isIncomplete: boolean;
}

/**
 * The landing after store setup: the shop exists, but nothing the seller adds
 * is publicly readable until an admin approves it (Admin\ProductController),
 * so this is where that expectation gets set — before they upload a catalogue
 * and wonder why the marketplace is empty.
 *
 * A page rather than a flash message on the product list: an empty catalogue
 * with a toast says none of this, and the toast is gone on the next click.
 */
export default function StoreCreated({
    storeName,
    hasProducts,
    isIncomplete,
}: Props) {
    const { t } = useTranslation();

    const steps = [
        { key: 'add', Icon: PackagePlus },
        { key: 'review', Icon: Clock },
        { key: 'live', Icon: Sparkles },
    ] as const;

    return (
        <>
            <Head title={t('seller.created.eyebrow')} />

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
                            {t('seller.created.eyebrow')}
                        </p>

                        <h1 className="mt-3 font-display text-[clamp(1.7rem,3.2vw,2.6rem)] leading-[1.1] tracking-[-0.02em] text-souk-title">
                            {t('seller.created.title')}
                        </h1>

                        <p className="mt-4 max-w-xl text-[15px] leading-relaxed text-souk-brown-deep/75">
                            {t('seller.created.body').replace(
                                ':name',
                                storeName,
                            )}
                        </p>

                        {/* ── What happens next ─────────────────────── */}
                        <section className="mt-9">
                            <h2 className="text-xs tracking-[0.14em] text-souk-brown-deep/50 uppercase">
                                {t('seller.created.steps_title')}
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
                                            {t(`seller.created.step_${key}`)}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </section>

                        {/* ── "fill it in later" reassurance ────────── */}
                        <div className="mt-9 rounded-2xl border border-souk-brown/15 bg-souk-cream/40 p-5">
                            <p className="text-sm font-medium text-souk-title">
                                {t('seller.created.later_title')}
                            </p>
                            <p className="mt-1.5 text-sm leading-relaxed text-souk-brown-deep/75">
                                {t('seller.created.later_body')}
                            </p>

                            {/* A shop opened from step one has no craft or
                                city yet, so it is missing from the buyer
                                filters. Point at the exact screen that fixes
                                that rather than leaving them to find it. */}
                            {isIncomplete && (
                                <Link
                                    href={route('seller.settings.index')}
                                    className="mt-3 inline-flex items-center gap-1.5 text-sm text-souk-brown underline underline-offset-4 transition-colors hover:text-souk-brown-deep"
                                >
                                    {t('seller.created.complete_profile')}
                                    <ArrowRight
                                        className="h-3.5 w-3.5 rtl:rotate-180"
                                        strokeWidth={1.6}
                                    />
                                </Link>
                            )}
                        </div>

                        {/* ── Actions ───────────────────────────────── */}
                        <div className="mt-9 flex flex-wrap items-center gap-3">
                            <Link
                                href={route('seller.dashboard')}
                                className="group inline-flex items-center gap-2 rounded-full bg-souk-brown px-7 py-3 text-sm text-souk-paper transition-colors hover:bg-souk-brown-deep"
                            >
                                {t('seller.created.to_dashboard')}
                                <ArrowRight
                                    className="h-4 w-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180"
                                    strokeWidth={1.6}
                                />
                            </Link>

                            {!hasProducts && (
                                <Link
                                    href={route('seller.products.create')}
                                    className="inline-flex items-center gap-2 rounded-full border border-souk-brown/25 px-6 py-3 text-sm text-souk-brown-deep/80 transition-colors hover:border-souk-brown/50 hover:text-souk-brown"
                                >
                                    <PackagePlus
                                        className="h-4 w-4"
                                        strokeWidth={1.6}
                                    />
                                    {t('seller.created.add_product')}
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
