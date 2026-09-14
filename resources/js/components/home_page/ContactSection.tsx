import { useTranslation } from '@/hooks/use-translation';
import Reveal from '@/components/home/Reveal';

import { Mail, MapPin, Phone } from 'lucide-react';
import MoroccoMap, {
    MOROCCO_ASPECT,
    projectMorocco,
} from '@/components/home/MoroccoMap';

const CONTACT_ROWS = [
    { key: 'email', Icon: Mail, href: 'mailto:' },
    { key: 'phone', Icon: Phone, href: 'tel:' },
    { key: 'address', Icon: MapPin, href: null },
] as const;

const MAP_CITIES = [
    { key: 'tanger', lat: 35.7595, lon: -5.7998, labelAbove: false },
    { key: 'fes', lat: 34.0331, lon: -5.0003, labelAbove: false },
    { key: 'safi', lat: 32.2994, lon: -9.2372, labelAbove: false },
    { key: 'marrakech', lat: 31.6295, lon: -7.9811, labelAbove: false },
    { key: 'agadir', lat: 30.4278, lon: -9.5981, labelAbove: false },
    { key: 'laayoune', lat: 27.1536, lon: -13.2033, labelAbove: false },
    { key: 'essaouira', lat: 31.5109436, lon: -9.7625422, labelAbove: false },
] as const;

export default function ContactSection() {
    const { t, isRTL } = useTranslation();
    return (
        <section className="relative overflow-hidden">
            <div className="container">
                <div className="mx-auto grid w-fit items-center gap-14 py-8 lg:grid-cols-2 lg:gap-8">
                    <Reveal from={isRTL ? 'right' : 'left'}>
                        <h2 className="mt-4 font-display text-[clamp(2rem,4.2vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.02em] text-souk-title">
                            <span className="block">
                                {t('welcome.contact.heading_line1')}
                            </span>
                            <span className="block text-souk-brown">
                                {t('welcome.contact.heading_line2')}
                            </span>
                        </h2>
                        <dl className="mt-10 space-y-7">
                            {CONTACT_ROWS.map(({ key, Icon, href }) => {
                                const value = t(`welcome.contact.${key}`);
                                const target = href
                                    ? `${href}${
                                          href === 'tel:'
                                              ? value.replace(/[^+\d]/g, '')
                                              : value
                                      }`
                                    : null;

                                return (
                                    <div
                                        key={key}
                                        className="flex items-start gap-4"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="grid size-11 shrink-0 place-items-center rounded-t-full rounded-b-md bg-souk-brown/10 text-souk-brown ring-1 ring-souk-brown/15"
                                        >
                                            <Icon
                                                className="size-[18px]"
                                                strokeWidth={1.6}
                                            />
                                        </span>

                                        <div>
                                            <dt className="text-[11px] font-semibold tracking-[0.18em] text-souk-brown-deep/55 uppercase rtl:tracking-normal">
                                                {t(
                                                    `welcome.contact.${key}_label`,
                                                )}
                                            </dt>
                                            <dd
                                                dir={target ? 'ltr' : undefined}
                                                className="mt-1 font-display text-lg text-souk-title rtl:text-end"
                                            >
                                                {target ? (
                                                    <a
                                                        href={target}
                                                        className="transition-colors hover:text-souk-brown"
                                                    >
                                                        {value}
                                                    </a>
                                                ) : (
                                                    value
                                                )}
                                            </dd>
                                        </div>
                                    </div>
                                );
                            })}
                        </dl>
                    </Reveal>

                    <Reveal from={isRTL ? 'left' : 'right'} delay={120}>
                        <div
                            className="relative mx-auto w-full max-w-sm lg:mx-0 lg:max-w-md"
                            style={{ aspectRatio: MOROCCO_ASPECT }}
                        >
                            <MoroccoMap
                                className="h-full w-full text-souk-brown"
                                fillOpacity={0.14}
                                strokeOpacity={0.4}
                            />

                            {MAP_CITIES.map(
                                ({ key, lon, lat, labelAbove }, i) => {
                                    const { left, top } = projectMorocco(
                                        lon,
                                        lat,
                                    );

                                    return (
                                        <div
                                            key={key}
                                            className="absolute -translate-x-1/2 -translate-y-1/2"
                                            style={{
                                                left: `${left}%`,
                                                top: `${top}%`,
                                            }}
                                        >
                                            <span className="relative flex h-2.5 w-2.5">
                                                <span
                                                    className="absolute inline-flex h-full w-full rounded-full bg-souk-brown/60 motion-safe:animate-ping"
                                                    /* Offset so the four pulse in
                                                   turn rather than as one
                                                   flashing block. */
                                                    style={{
                                                        animationDelay: `${i * 600}ms`,
                                                    }}
                                                />
                                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-souk-brown ring-2 ring-souk-peach" />
                                            </span>

                                            <span
                                                className={`absolute left-1/2 -translate-x-1/2 font-display text-xs font-semibold whitespace-nowrap text-souk-brown-deep sm:text-sm ${
                                                    labelAbove
                                                        ? 'bottom-4'
                                                        : 'top-4'
                                                }`}
                                            >
                                                {t(`welcome.map.city.${key}`)}
                                            </span>
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    </Reveal>
                </div>
            </div>
        </section>
    );
}
