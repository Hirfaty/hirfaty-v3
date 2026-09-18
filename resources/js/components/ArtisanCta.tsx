import { Link } from '@inertiajs/react';
import { ArrowRight, Truck } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import { useTranslation } from '@/hooks/use-translation';
import zellij from '@/images/zellij-pattern.png';
import { sell } from '@/routes';

/**
 * The closing "become a seller" banner — shared by the home and about pages
 * (and anywhere else a public marketing page wants to close on the same
 * pitch) so the copy and styling only need to change in one place.
 */
export default function ArtisanCta() {
    const { t } = useTranslation();

    return (
        <section className="bg-souk-paper py-12 lg:py-4 lg:pb-0">
            <div className="container px-2!">
                <Reveal from="scale">
                    <div className="relative overflow-hidden rounded-[2rem] bg-souk-brown px-8 py-16 text-center sm:px-16">
                        {/* The same zellij photograph the other dark panels
                            (the about page's hero, the auth screens) tile
                            behind their copy — `mix-blend-screen` at a low
                            opacity so only the tile's highlights show through
                            the brown fill, same treatment those use. */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 opacity-[0.08] mix-blend-screen"
                            style={{
                                backgroundImage: `url(${zellij})`,
                                backgroundSize: '320px',
                            }}
                        />
                        <div className="relative mx-auto max-w-2xl">
                            {/* <p className="font-arabic text-lg text-souk-cream/80">
                                {t('welcome.seller_cta.ar_line')}
                            </p> */}
                            <h2 className="mt-5 font-display text-[clamp(2rem,4.5vw,3.5rem)] leading-tight font-bold text-white">
                                {t('welcome.seller_cta.title')}{' '}
                                <span className="text-souk-cream italic">
                                    {t('welcome.seller_cta.subtitle')}
                                </span>
                            </h2>
                            <p className="mt-6 leading-relaxed text-souk-cream/80">
                                {t('welcome.seller_cta.description')}
                            </p>

                            <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                                {/* The artisan form, not the buyer flow:
                                    /register is now email-only signup for
                                    shoppers. */}
                                <Link
                                    href={sell()}
                                    className="group inline-flex items-center gap-3 rounded-xl bg-souk-cream px-9 py-4 font-display text-lg text-souk-brown-deep transition-transform duration-300 hover:-translate-y-0.5"
                                >
                                    {t('welcome.seller_cta.button')}
                                    <ArrowRight
                                        className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                                <span className="inline-flex items-center gap-2 text-sm text-souk-cream/70">
                                    <Truck
                                        className="h-4 w-4"
                                        strokeWidth={1.5}
                                    />
                                    {t('welcome.seller_cta.note')}
                                </span>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
