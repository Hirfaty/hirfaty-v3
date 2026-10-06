import StaticPage, { ProseSection } from '@/components/StaticPage';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Shipping policy.
 *
 * Every figure on this page is the one the code actually charges: the flat 40
 * MAD domestic rate comes from `DeliveryRateSeeder`, the single delivery
 * charge per basket from `CheckoutService` (which attaches the whole fee to
 * one order of a split purchase), and card-only payment (Payzone) from
 * `CheckoutRequest::PAYMENT_METHODS`. Keep them in step if any of those move.
 */
export default function ShippingPolicy() {
    const { t } = useTranslation();

    return (
        <StaticPage
            title={t('pages.shipping.title')}
            eyebrow={t('pages.shipping.eyebrow')}
            heading={t('pages.shipping.heading')}
            lead={t('pages.shipping.lead')}
        >
            <div className="py-10">
                <ProseSection heading={t('pages.shipping.morocco.heading')}>
                    <p>{t('pages.shipping.morocco.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.shipping.abroad.heading')}>
                    <p>{t('pages.shipping.abroad.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.shipping.payment.heading')}>
                    <p>{t('pages.shipping.payment.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.shipping.tracking.heading')}>
                    <p>{t('pages.shipping.tracking.body')}</p>
                </ProseSection>

                <ProseSection heading={t('pages.shipping.handmade.heading')}>
                    <p>{t('pages.shipping.handmade.body')}</p>
                </ProseSection>
            </div>
        </StaticPage>
    );
}
