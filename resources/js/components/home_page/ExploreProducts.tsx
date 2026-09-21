import ProductShowcase from '@/components/home/ProductShowcase';
import type { ShowcaseProduct } from '@/components/home/ProductShowcase';
import Reveal from '@/components/home/Reveal';
import { useCurrency } from '@/hooks/use-currency';
import { useTranslation } from '@/hooks/use-translation';
import jewelry from '@/images/cat-jewelry.jpg';
import leather from '@/images/cat-leather.jpg';
import pottery from '@/images/cat-pottery.jpg';
import spice from '@/images/cat-spice.jpg';
import textile from '@/images/cat-textile.jpg';
import wood from '@/images/cat-wood.jpg';

const SHOWCASE = [pottery, leather, wood, textile, jewelry, spice];

export function ExploreProducts({
    featuredProducts,
}: {
    featuredProducts: ShowcaseProduct[];
}) {
    const { t, isRTL } = useTranslation();
    const { money } = useCurrency();

    return (
        <section className="relative bg-souk-paper py-6 lg:py-8">
            <div className="container">
                <Reveal delay={80}>
                    <h2 className="text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                        {t('welcome.products.heading')}
                    </h2>
                </Reveal>

                {featuredProducts.length > 0 ? (
                    <Reveal delay={160} className="mt-6 lg:mt-8">
                        <ProductShowcase
                            products={featuredProducts}
                            money={money}
                            labels={{
                                prev: t('welcome.products.prev'),
                                next: t('welcome.products.next'),
                                spotlightPrev: t(
                                    'welcome.products.spotlight_prev',
                                ),
                                spotlightNext: t(
                                    'welcome.products.spotlight_next',
                                ),
                                viewAll: t('welcome.products.view_all'),
                            }}
                            isRTL={isRTL}
                        />
                    </Reveal>
                ) : (
                    <div className="mt-6">
                        <div className="grid gap-x-6 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                            {SHOWCASE.slice(0, 4).map((src, i) => (
                                <Reveal key={src} delay={i * 100}>
                                    <div className="relative aspect-[4/5] bg-souk-brown/5">
                                        <img
                                            src={src}
                                            alt=""
                                            className="h-full w-full object-cover opacity-70"
                                            loading="lazy"
                                        />
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                        <p className="mt-8 text-center text-souk-brown-deep/70">
                            {t('welcome.products.empty')}
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
