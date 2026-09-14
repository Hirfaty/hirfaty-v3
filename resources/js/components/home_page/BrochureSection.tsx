import { useTranslation } from '@/hooks/use-translation';
import Reveal from '../home/Reveal';
import brochure from '@/images/brochure/brochure.jpeg';
import { Download } from 'lucide-react';

export default function BrochureSection() {
    const { t } = useTranslation();

    return (
        <section className="py-8 lg:py-8">
            <div className="container">
                <Reveal from="scale">
                    <div className="relative isolate overflow-hidden rounded-2xl bg-souk-brown">
                        <div
                            aria-hidden="true"
                            className="absolute inset-y-0 end-0 -z-10 hidden lg:block lg:w-1/2"
                        >
                            <img
                                src={brochure}
                                alt=""
                                loading="lazy"
                                className="h-full w-full object-cover"
                            />

                            <div className="absolute inset-0 bg-gradient-to-l from-transparent via-souk-brown/55 to-souk-brown rtl:bg-gradient-to-r" />
                        </div>
                        <div className="max-w-xl px-6 py-10 sm:px-8 sm:py-12 lg:w-1/2 lg:py-14">
                            <p className="font-display text-2xl leading-snug text-white">
                                {t('welcome.catalogue.heading')}
                            </p>
                            <p className="mt-1.5 text-sm text-souk-cream/75">
                                {t('welcome.catalogue.lead')}
                            </p>
                            <a
                                href="#"
                                onClick={(e) => e.preventDefault()}
                                className="mt-7 inline-flex items-center justify-center gap-2 rounded-xl bg-souk-cream px-6 py-3 text-sm font-semibold tracking-[0.04em] text-souk-brown-deep uppercase transition-colors hover:bg-white"
                            >
                                <Download
                                    className="h-4 w-4 shrink-0"
                                    strokeWidth={2}
                                />
                                {t('welcome.catalogue.button')}
                            </a>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
