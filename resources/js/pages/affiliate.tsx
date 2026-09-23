import { Check, Link2, Send, Wallet } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import StaticPage from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

const STEPS = [
    { key: 'step1', Icon: Send },
    { key: 'step2', Icon: Link2 },
    { key: 'step3', Icon: Wallet },
] as const;

const CRITERIA = ['point1', 'point2', 'point3'] as const;

/**
 * The affiliate programme, as a page only.
 *
 * Nothing behind it yet: there is no partner model, no referral link, no
 * attribution. Applying is an email, which is why the call to action is a
 * `mailto:` rather than a form that would need somewhere to post to.
 */
export default function Affiliate() {
    const { t } = useTranslation();

    return (
        <StaticPage
            title={t('pages.affiliate.title')}
            eyebrow={t('pages.affiliate.eyebrow')}
            heading={t('pages.affiliate.heading')}
            lead={t('pages.affiliate.lead')}
        >
            {/* ── Three steps ───────────────────────────────── */}
            <section className="py-16 lg:py-20">
                <div className="container">
                    <h2 className="font-display text-3xl text-souk-title">
                        {t('pages.affiliate.how.heading')}
                    </h2>

                    <ol className="mt-8 grid gap-8 md:grid-cols-3">
                        {STEPS.map(({ key, Icon }, i) => (
                            <Reveal key={key} delay={100 + i * 110}>
                                <li className="rounded-2xl border border-souk-brown/12 bg-white p-6">
                                    <span className="inline-flex h-11 w-11 items-center justify-center rounded-t-full rounded-b-md bg-souk-brown/8 text-souk-brown">
                                        <Icon
                                            className="h-5 w-5"
                                            strokeWidth={1.6}
                                        />
                                    </span>
                                    <h3 className="mt-4 font-display text-xl text-souk-title">
                                        {t(`pages.affiliate.how.${key}.title`)}
                                    </h3>
                                    <p className="mt-2 text-[15px] leading-relaxed text-souk-brown-deep/75">
                                        {t(`pages.affiliate.how.${key}.body`)}
                                    </p>
                                </li>
                            </Reveal>
                        ))}
                    </ol>
                </div>
            </section>

            {/* ── Who can join ──────────────────────────────── */}
            <section className="bg-souk-peach py-16 lg:py-20">
                <div className="container grid gap-10 lg:grid-cols-2 lg:items-start">
                    <div>
                        <h2 className="font-display text-3xl text-souk-title">
                            {t('pages.affiliate.who.heading')}
                        </h2>
                        <p className="mt-4 text-[15px] leading-relaxed text-souk-brown-deep/80">
                            {t('pages.affiliate.who.body')}
                        </p>
                    </div>

                    <ul className="space-y-3">
                        {CRITERIA.map((key) => (
                            <li key={key} className="flex items-start gap-3">
                                <Check
                                    className="mt-0.5 h-4 w-4 shrink-0 text-souk-brown"
                                    strokeWidth={2}
                                />
                                <span className="text-[15px] leading-relaxed text-souk-brown-deep/85">
                                    {t(`pages.affiliate.who.${key}`)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            {/* ── Terms, then the application ───────────────── */}
            <section className="py-16 lg:py-20">
                <div className="container max-w-3xl">
                    <h2 className="font-display text-3xl text-souk-title">
                        {t('pages.affiliate.terms.heading')}
                    </h2>
                    <p className="mt-4 text-[15px] leading-relaxed text-souk-brown-deep/80">
                        {t('pages.affiliate.terms.body')}
                    </p>

                    <div className="mt-10 rounded-2xl border border-souk-brown/15 bg-white p-8">
                        <h3 className="font-display text-2xl text-souk-title">
                            {t('pages.affiliate.cta.heading')}
                        </h3>
                        <p className="mt-3 text-[15px] leading-relaxed text-souk-brown-deep/75">
                            {t('pages.affiliate.cta.body')}
                        </p>
                        {/*
                         * mailto, not a form: there is no partner record to
                         * post one into yet, and a form that quietly went
                         * nowhere would be worse than no form.
                         */}
                        <a
                            href={`mailto:${t('welcome.contact.email')}?subject=${encodeURIComponent(t('pages.affiliate.title'))}`}
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-souk-title px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-souk-brown-deep"
                        >
                            {t('pages.affiliate.cta.button')}
                        </a>
                        <p className="mt-3 text-sm text-souk-brown-deep/60">
                            {t('welcome.contact.email')}
                        </p>
                    </div>
                </div>
            </section>
        </StaticPage>
    );
}
