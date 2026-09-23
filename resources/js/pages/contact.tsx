import { Head, Link } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import Reveal from '@/components/home/Reveal';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';
import zellij from '@/images/zellij-pattern.png';
import { login } from '@/routes';

/**
 * The contact details themselves come from `welcome.contact.*`, which the
 * homepage's `ContactSection` already reads. Copying them into `pages.php`
 * would mean changing the phone number in two files and, in practice,
 * remembering only one of them.
 *
 * `href` builds the mailto:/tel: link; `null` means the value is not
 * actionable. Opening hours have no counterpart on the homepage, so that one
 * row is read from `pages.php` instead — see `source`.
 */
const ROWS = [
    { key: 'email', source: 'welcome.contact', Icon: Mail, href: 'mailto:' },
    { key: 'phone', source: 'welcome.contact', Icon: Phone, href: 'tel:' },
    { key: 'address', source: 'welcome.contact', Icon: MapPin, href: null },
    // { key: 'hours', source: 'pages.contact', Icon: Clock, href: null },
] as const;

export default function Contact() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('pages.contact.title')} />

            <div className="min-h-screen bg-souk-paper">
                <SiteHeader />

                {/* ───────────────────────── Hero ───────────────────────── */}
                <section className="relative overflow-hidden bg-souk-brown">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 opacity-[0.07] mix-blend-screen"
                        style={{
                            backgroundImage: `url(${zellij})`,
                            backgroundSize: '340px',
                        }}
                    />

                    <div className="relative container py-20 lg:py-24">
                        <Reveal>
                            <p className="text-[11px] tracking-[0.22em] text-souk-cream/70 uppercase rtl:tracking-normal">
                                {t('pages.contact.eyebrow')}
                            </p>
                        </Reveal>

                        <Reveal delay={80}>
                            <h1 className="mt-5 font-display text-[clamp(2.25rem,5.5vw,4rem)] leading-[1.02] font-bold tracking-[-0.02em] text-white">
                                {t('pages.contact.heading')}
                            </h1>
                        </Reveal>

                        <Reveal delay={180}>
                            <p className="mt-6 max-w-2xl text-xl leading-relaxed text-souk-cream/85">
                                {t('pages.contact.lead')}
                            </p>
                        </Reveal>
                    </div>
                </section>

                {/* ─────────────────────── The details ─────────────────────── */}
                <section className="py-16 lg:py-20">
                    <div className="container">
                        <dl className="grid gap-8 sm:grid-cols-2">
                            {ROWS.map(({ key, source, Icon, href }, i) => {
                                const value = t(`${source}.${key}`);
                                const target = href
                                    ? `${href}${
                                          href === 'tel:'
                                              ? // tel: wants digits and a
                                                // leading +, nothing else.
                                                value.replace(/[^+\d]/g, '')
                                              : value
                                      }`
                                    : null;

                                return (
                                    <Reveal key={key} delay={100 + i * 90}>
                                        <div className="flex items-start gap-4 rounded-md border border-souk-brown/10 bg-white p-6">
                                            <span
                                                aria-hidden="true"
                                                className="grid size-11 shrink-0 place-items-center rounded-t-full rounded-b-md bg-souk-brown/10 text-souk-brown ring-1 ring-souk-brown/15"
                                            >
                                                <Icon
                                                    className="size-[18px]"
                                                    strokeWidth={1.6}
                                                />
                                            </span>

                                            <div className="min-w-0">
                                                <dt className="text-[11px] font-semibold tracking-[0.18em] text-souk-brown-deep/55 uppercase rtl:tracking-normal">
                                                    {t(
                                                        `${source}.${key}_label`,
                                                    )}
                                                </dt>
                                                <dd
                                                    // An address or a phone
                                                    // number is never written
                                                    // right-to-left; letting
                                                    // the paragraph direction
                                                    // take over reorders it.
                                                    dir={
                                                        target
                                                            ? 'ltr'
                                                            : undefined
                                                    }
                                                    className="mt-1 font-display text-lg break-words text-souk-title rtl:text-end"
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
                                    </Reveal>
                                );
                            })}
                        </dl>
                    </div>
                </section>

                {/* ──────────────────── For the artisans ──────────────────── */}
                <section className="pb-16 lg:pb-20">
                    <div className="container">
                        <Reveal from="scale">
                            <div className="rounded-md bg-souk-cream px-6 py-12 text-center sm:px-10">
                                <h2 className="font-display text-2xl text-souk-title">
                                    {t('pages.contact.seller.heading')}
                                </h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-souk-title/70">
                                    {t('pages.contact.seller.body')}
                                </p>
                                <Link
                                    href={login()}
                                    className="mt-7 inline-flex items-center justify-center rounded-xl bg-souk-brown px-7 py-3.5 text-sm font-semibold tracking-[0.04em] text-white uppercase transition-colors hover:bg-souk-brown-deep"
                                >
                                    {t('pages.contact.seller.cta')}
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
