import { Head, Link } from '@inertiajs/react';
import { Mail, Phone, ShieldOff } from 'lucide-react';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase rtl:tracking-normal';

/**
 * Where a blocked account lands after being signed out — see
 * BlockedAccountService. The contact details come from `welcome.contact.*`,
 * the same source as the contact page, so they only live in one place.
 */
export default function AccountBlocked() {
    const { t } = useTranslation();

    const email = t('welcome.contact.email');
    const phone = t('welcome.contact.phone');

    return (
        <div className="min-h-screen bg-souk-paper">
            <Head title={t('common.account_blocked.title')} />

            <SiteHeader />

            <section className="relative overflow-hidden">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 motif-zellij"
                    style={
                        {
                            '--pattern-color': 'rgba(130, 71, 42, 0.05)',
                            '--pattern-size': '64px',
                        } as React.CSSProperties
                    }
                />

                <div className="relative container flex flex-col items-center px-6 py-24 text-center lg:py-32">
                    <ShieldOff
                        className="h-10 w-10 text-souk-brown/40"
                        strokeWidth={1.4}
                    />

                    <p className={`mt-8 text-souk-brown ${EYEBROW}`}>
                        {t('common.account_blocked.eyebrow')}
                    </p>

                    <h1 className="mt-4 max-w-xl font-display text-[clamp(1.75rem,3.5vw,2.6rem)] leading-[1.15] tracking-[-0.01em] text-souk-title">
                        {t('common.account_blocked.title')}
                    </h1>

                    <p className="mt-4 max-w-md text-[15px] leading-relaxed text-souk-brown-deep/75">
                        {t('common.account_blocked.body')}
                    </p>

                    <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                        <a
                            href={`mailto:${email}`}
                            className="inline-flex items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            <Mail className="h-4 w-4" strokeWidth={1.6} />
                            {email}
                        </a>

                        <a
                            href={`tel:${phone.replace(/\s+/g, '')}`}
                            dir="ltr"
                            className="inline-flex items-center justify-center gap-2.5 rounded-xl border border-souk-brown/25 px-6 py-3.5 text-sm font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown"
                        >
                            <Phone className="h-4 w-4" strokeWidth={1.6} />
                            {phone}
                        </a>
                    </div>

                    <Link
                        href={route('contact')}
                        className="mt-6 text-sm text-souk-brown underline-offset-4 hover:underline"
                    >
                        {t('common.account_blocked.contact_page')}
                    </Link>
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
