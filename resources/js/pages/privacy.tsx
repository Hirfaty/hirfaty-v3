import StaticPage, { ProseSection } from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

/** The CNDP's logo, shown in the loi 09-08 box (and in the site footer). */
const CNDP_LOGO = '/images/legal/cndp.png';

/** Where loi 09-08 requests go, as stated in the CNDP declaration. */
const DATA_RIGHTS_EMAIL = 'welcome.to.hirfaty@gmail.com';

/**
 * Privacy policy.
 *
 * Also closes a real gap: the transactional emails have linked to `/privacy`
 * since they were written, and until now that link 404'd.
 *
 * Plain prose rather than legalese, and deliberately describing only what the
 * app actually does — an account, an order, and the IP-derived currency
 * default (CurrencyService). Nothing here claims a practice the code does not
 * have.
 */
export default function Privacy() {
    const { t } = useTranslation();

    return (
        <StaticPage
            title={t('pages.privacy.title')}
            eyebrow={t('pages.privacy.eyebrow')}
            heading={t('pages.privacy.heading')}
            lead={t('pages.privacy.lead')}
        >
            <div className="py-10">
                <div className="container max-w-3xl">
                    <p className="text-sm text-souk-brown-deep/55">
                        {t('pages.privacy.updated')}
                    </p>

                    {/* Loi 09-08: the declaration the CNDP registered, at the
                        top of the page with its logo, as the receipt asks. */}
                    <section
                        aria-labelledby="cndp-compliance"
                        className="mt-6 rounded-2xl border border-souk-brown/15 bg-souk-cream/60 p-6"
                    >
                        <div className="flex items-center gap-4">
                            <img
                                src={CNDP_LOGO}
                                alt={t('pages.privacy.compliance.logo_alt')}
                                width={340}
                                height={227}
                                className="h-20 w-auto shrink-0 rounded-md bg-white px-2 py-1"
                            />
                            <h2
                                id="cndp-compliance"
                                className="font-display text-xl leading-snug text-souk-title"
                            >
                                {t('pages.privacy.compliance.heading')}
                            </h2>
                        </div>
                        <div className="mt-4 space-y-3 text-[15px] leading-relaxed text-souk-brown-deep/80">
                            <p>{t('pages.privacy.compliance.publisher')}</p>
                            <p>{t('pages.privacy.compliance.declaration')}</p>
                            <p>
                                {t('pages.privacy.compliance.rights')}{' '}
                                <a
                                    href={`mailto:${DATA_RIGHTS_EMAIL}`}
                                    dir="ltr"
                                    className="text-souk-title underline underline-offset-2"
                                >
                                    {DATA_RIGHTS_EMAIL}
                                </a>
                            </p>
                        </div>
                    </section>
                </div>

                <ProseSection heading={t('pages.privacy.collect.heading')}>
                    <p>{t('pages.privacy.collect.body1')}</p>
                    <p>{t('pages.privacy.collect.body2')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.privacy.use.heading')}>
                    <p>{t('pages.privacy.use.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.privacy.share.heading')}>
                    <p>{t('pages.privacy.share.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.privacy.rights.heading')}>
                    <p>{t('pages.privacy.rights.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.privacy.contact.heading')}>
                    <p>{t('pages.privacy.contact.body')}</p>
                    {/*
                     * Read from `welcome.contact.*`, the one place the address
                     * and phone number live — the contact page and the
                     * homepage already read the same keys, so there is no
                     * second copy to drift.
                     */}
                    <p className="text-souk-title">
                        <a
                            href={`mailto:${t('welcome.contact.email')}`}
                            className="underline underline-offset-2"
                        >
                            {t('welcome.contact.email')}
                        </a>
                        <br />
                        {t('welcome.contact.phone')}
                        <br />
                        {t('welcome.contact.address')}
                    </p>
                </ProseSection>
            </div>
        </StaticPage>
    );
}
