import { Link } from '@inertiajs/react';
import { Cake, Gift, ShoppingBag, Star, UserPlus } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import StaticPage from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

const EARN = [
    { key: 'shop', Icon: ShoppingBag },
    { key: 'signup', Icon: UserPlus },
    { key: 'review', Icon: Star },
    { key: 'birthday', Icon: Cake },
] as const;

const TIERS = ['silver', 'gold', 'platinum'] as const;

/**
 * The rewards programme, as a page only.
 *
 * There is no points ledger behind this — nothing here reads a balance, and
 * the closing section says the programme is still being built rather than
 * letting a signed-in buyer wonder where their points are.
 */
export default function Rewards() {
    const { t } = useTranslation();

    return (
        <StaticPage
            title={t('pages.rewards.title')}
            eyebrow={t('pages.rewards.eyebrow')}
            heading={t('pages.rewards.heading')}
            lead={t('pages.rewards.lead')}
        >
            {/* ── Earning ───────────────────────────────────── */}
            <section className="py-16 lg:py-20">
                <div className="container">
                    <h2 className="font-display text-3xl text-souk-title">
                        {t('pages.rewards.earn.heading')}
                    </h2>

                    <ul className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {EARN.map(({ key, Icon }, i) => (
                            <Reveal key={key} delay={90 + i * 90}>
                                <li className="h-full rounded-2xl border border-souk-brown/12 bg-white p-6">
                                    <span className="inline-flex h-11 w-11 items-center justify-center rounded-t-full rounded-b-md bg-souk-brown/8 text-souk-brown">
                                        <Icon
                                            className="h-5 w-5"
                                            strokeWidth={1.6}
                                        />
                                    </span>
                                    <h3 className="mt-4 font-display text-lg text-souk-title">
                                        {t(`pages.rewards.earn.${key}.title`)}
                                    </h3>
                                    <p className="mt-1.5 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                        {t(`pages.rewards.earn.${key}.body`)}
                                    </p>
                                </li>
                            </Reveal>
                        ))}
                    </ul>
                </div>
            </section>

            {/* ── Spending ──────────────────────────────────── */}
            <section className="bg-souk-peach py-16 lg:py-20">
                <div className="container max-w-3xl text-center">
                    <h2 className="font-display text-3xl text-souk-title">
                        {t('pages.rewards.spend.heading')}
                    </h2>
                    <p className="mt-4 text-lg leading-relaxed text-souk-brown-deep/85">
                        {t('pages.rewards.spend.body')}
                    </p>
                    <p className="mt-4 text-sm leading-relaxed text-souk-brown-deep/65">
                        {t('pages.rewards.spend.note')}
                    </p>
                </div>
            </section>

            {/* ── Tiers ─────────────────────────────────────── */}
            <section className="py-16 lg:py-20">
                <div className="container">
                    <h2 className="font-display text-3xl text-souk-title">
                        {t('pages.rewards.tiers.heading')}
                    </h2>
                    <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                        {t('pages.rewards.tiers.body')}
                    </p>

                    <ul className="mt-8 grid gap-5 md:grid-cols-3">
                        {TIERS.map((key, i) => (
                            <Reveal key={key} delay={100 + i * 110}>
                                <li className="h-full rounded-2xl border border-souk-brown/12 bg-white p-6">
                                    <p className="text-[11px] tracking-[0.18em] text-souk-brown uppercase rtl:tracking-normal">
                                        {t(`pages.rewards.tiers.${key}`)}
                                    </p>
                                    <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/80">
                                        {t(`pages.rewards.tiers.${key}_body`)}
                                    </p>
                                </li>
                            </Reveal>
                        ))}
                    </ul>
                </div>
            </section>

            {/* ── Not live yet ──────────────────────────────── */}
            <section className="pb-20">
                <div className="container">
                    <div className="flex flex-col items-start gap-5 rounded-2xl border border-dashed border-souk-brown/25 bg-souk-brown/[0.04] p-8 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-4">
                            <Gift
                                className="mt-0.5 h-6 w-6 shrink-0 text-souk-brown"
                                strokeWidth={1.6}
                            />
                            <div>
                                <h2 className="font-display text-xl text-souk-title">
                                    {t('pages.rewards.soon.heading')}
                                </h2>
                                <p className="mt-1.5 max-w-xl text-[15px] leading-relaxed text-souk-brown-deep/75">
                                    {t('pages.rewards.soon.body')}
                                </p>
                            </div>
                        </div>

                        <Link
                            href={route('products.index')}
                            className="shrink-0 rounded-lg bg-souk-title px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('pages.rewards.soon.button')}
                        </Link>
                    </div>
                </div>
            </section>
        </StaticPage>
    );
}
