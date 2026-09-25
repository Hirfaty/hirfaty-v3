import { Link } from '@inertiajs/react';
import { AtelierArchIcon, StorefrontIcon } from '@/components/home/CraftIcons';
import HeroSlider from '@/components/home/HeroSlider';
import Reveal from '@/components/home/Reveal';
import { useTranslation } from '@/hooks/use-translation';
import hero1 from '@/images/hero/hero-1.jpeg';
import hero2 from '@/images/hero/hero-2.jpg';
import hero3 from '@/images/hero/hero-3.jpg';

const HERO_SLIDES = [
    { src: hero1, altKey: 'welcome.hero.alt' },
    { src: hero2, altKey: 'welcome.hero.alt_textile' },
    { src: hero3, altKey: 'welcome.hero.alt_zellige' },
] as const;

export default function HeroSection() {
    const { t, isRTL } = useTranslation();

    return (
        <section className="overflow-x-clip bg-souk-paper px-5 pb-3 sm:px-8 sm:pb-4 lg:px-12 lg:pb-2">
            <HeroSlider
                slides={HERO_SLIDES.map((slide) => ({
                    src: slide.src,
                    alt: t(slide.altKey),
                }))}
                label={t('welcome.hero.slides_label')}
                prevLabel={t('welcome.hero.prev')}
                nextLabel={t('welcome.hero.next')}
                isRTL={isRTL}
            >
                <h1 className="font-display text-[clamp(1.75rem,4.8vw,4rem)] leading-[1.08] tracking-[0.04em] text-white uppercase rtl:tracking-normal">
                    <Reveal delay={80}>
                        <span className="block">{t('welcome.hero.line')}</span>
                    </Reveal>
                </h1>

                <Reveal delay={280}>
                    <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-souk-cream sm:mt-4 sm:text-xl">
                        {t('welcome.hero.lead')}
                    </p>
                </Reveal>

                <Reveal delay={360}>
                    <div className="mx-auto mt-5 flex max-w-64 flex-col items-stretch gap-2.5 sm:mt-6 sm:max-w-none sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-3">
                        <Link
                            href={route('products.index')}
                            className="group pointer-events-auto relative inline-flex items-center justify-center gap-2.5 overflow-hidden rounded-md bg-souk-brown px-5 py-2.5 text-sm font-semibold text-souk-cream shadow-lg shadow-black/25 hover:bg-souk-brown/90 sm:px-7 sm:py-3.5 sm:text-base"
                        >
                            <StorefrontIcon className="h-5 w-5 shrink-0" />
                            {t('welcome.hero.cta_primary')}
                        </Link>
                        <Link
                            href={route('sell')}
                            className="pointer-events-auto inline-flex items-center justify-center gap-2.5 rounded-md border-[1.5px] border-souk-cream/40 bg-souk-cream/15 px-5 py-2.5 text-sm font-semibold text-souk-cream backdrop-blur transition-colors duration-300 hover:bg-souk-cream/25 sm:px-7 sm:py-3.5 sm:text-base"
                        >
                            <AtelierArchIcon className="h-5 w-5 shrink-0" />
                            {t('welcome.hero.cta_secondary')}
                        </Link>
                    </div>
                </Reveal>
            </HeroSlider>
        </section>
    );
}
