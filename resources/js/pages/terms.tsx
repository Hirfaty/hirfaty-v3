import StaticPage, { ProseSection } from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Terms of Service.
 *
 * Closes the same kind of gap `privacy.tsx` did: the registration checkbox
 * (`auth/login/details.tsx`, `auth/seller/details.tsx`) and several
 * transactional email footers have linked to `/terms` since they were
 * written, and until now it 404'd.
 *
 * Plain prose, and grounded in what the app actually does rather than
 * boilerplate a lawyer would recognise from nowhere in particular:
 * card payment in MAD through Payzone (CheckoutRequest::PAYMENT_METHODS), orders
 * confirmed the moment they are paid (PaymentService), cancellation by
 * writing in, with a refund to the card (OrderStatusService),
 * product moderation (Product::isPendingReview() etc.), and account
 * suspension via User::is_banned.
 */
export default function Terms() {
    const { t } = useTranslation();

    return (
        <StaticPage
            title={t('pages.terms.title')}
            eyebrow={t('pages.terms.eyebrow')}
            heading={t('pages.terms.heading')}
            lead={t('pages.terms.lead')}
        >
            <div className="py-10">
                <div className="container max-w-3xl">
                    <p className="text-sm text-souk-brown-deep/55">
                        {t('pages.terms.updated')}
                    </p>
                </div>

                <ProseSection heading={t('pages.terms.marketplace.heading')}>
                    <p>{t('pages.terms.marketplace.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.accounts.heading')}>
                    <p>{t('pages.terms.accounts.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.orders.heading')}>
                    <p>{t('pages.terms.orders.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.cancellation.heading')}>
                    <p>{t('pages.terms.cancellation.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.listings.heading')}>
                    <p>{t('pages.terms.listings.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.reviews.heading')}>
                    <p>{t('pages.terms.reviews.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.sellers.heading')}>
                    <p>{t('pages.terms.sellers.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.suspension.heading')}>
                    <p>{t('pages.terms.suspension.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.changes.heading')}>
                    <p>{t('pages.terms.changes.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.law.heading')}>
                    <p>{t('pages.terms.law.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.terms.contact.heading')}>
                    <p>{t('pages.terms.contact.body')}</p>
                    {/*
                     * Read from `welcome.contact.*`, the one place the address
                     * and phone number live — see privacy.tsx for the same
                     * choice.
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
