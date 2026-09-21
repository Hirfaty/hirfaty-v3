import StaticPage, { ProseSection } from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

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
