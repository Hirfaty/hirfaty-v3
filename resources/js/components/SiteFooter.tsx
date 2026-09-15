import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import VaseIcon from '@/components/home/VaseIcon';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import newsletter from '@/routes/newsletter';

function NewsletterForm() {
    const { t } = useTranslation();
    const page = usePage<{ flash?: { success?: string } }>();
    const form = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(newsletter.store.url(), {
            errorBag: 'newsletter',

            preserveScroll: true,
            onSuccess: () => {
                form.reset('email');
                toast.success(
                    page.props.flash?.success ??
                        t('common.footer.newsletter.success'),
                );
            },
        });
    };

    return (
        <form onSubmit={submit} className="w-full max-w-md">
            <div className="flex items-start gap-3">
                <div className="flex-1">
                    <label htmlFor="newsletter-email" className="sr-only">
                        {t('common.footer.newsletter.label')}
                    </label>
                    <input
                        id="newsletter-email"
                        type="email"
                        name="email"
                        autoComplete="email"
                        dir="ltr"
                        placeholder={t('common.footer.newsletter.placeholder')}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        aria-invalid={form.errors.email ? true : undefined}
                        aria-describedby={
                            form.errors.email
                                ? 'newsletter-email-error'
                                : undefined
                        }
                        className="w-full border-b border-ink/25 bg-transparent px-1 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-soft/60 focus:border-ink focus:outline-none rtl:text-end"
                    />
                    {form.errors.email && (
                        <p
                            id="newsletter-email-error"
                            className="mt-2 text-xs text-terracotta"
                        >
                            {form.errors.email}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="group inline-flex shrink-0 cursor-pointer items-center gap-2 border-b border-ink/40 px-1 py-2.5 text-sm text-ink transition-colors hover:border-ink disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {t('common.footer.newsletter.submit')}
                    <ArrowRight
                        className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                        strokeWidth={1.75}
                    />
                </button>
            </div>
        </form>
    );
}

/**
 * The three link columns.
 *
 * Every entry points somewhere real. The previous version listed twelve links
 * that all pointed at `#` — pricing, a seller help centre, press, terms — for
 * pages that do not exist; a dead link in a footer is worse than no link, so
 * only the four that have a destination are left.
 */
const NAV_COLUMNS = [
    {
        titleKey: 'common.footer.buyers.title',
        links: [
            { labelKey: 'common.footer.buyers.explore', href: '/products' },
            { labelKey: 'common.footer.buyers.how', href: '/how-it-works' },
        ],
    },
    {
        titleKey: 'common.footer.artisans.title',
        links: [{ labelKey: 'common.footer.artisans.sell', href: '/register' }],
    },
    {
        titleKey: 'common.footer.company.title',
        links: [
            { labelKey: 'common.footer.company.contact', href: '/contact' },
        ],
    },
] as const;

export const SiteFooter = () => {
    const { t } = useTranslation();

    return (
        <footer className="relative mt-8 overflow-hidden border-t border-ink/15 bg-cream">
            <VaseIcon className="pointer-events-none absolute -bottom-10 -left-10 h-64 w-64 text-ink/5 md:h-80 md:w-80" />

            <div className="relative container grid grid-cols-2 gap-10 py-16 md:grid-cols-12">
                <div className="col-span-2 md:col-span-4">
                    <div className="flex items-center gap-2">
                        <img
                            src={logo}
                            alt="Hirfaty"
                            className="h-8 w-8 object-contain"
                        />
                        <span className="font-display text-lg text-ink">
                            Hirfaty<span className="text-terracotta">.</span>
                        </span>
                    </div>
                    <p className="mt-5 max-w-xs text-sm leading-relaxed text-ink-soft">
                        {t('common.footer.blurb')}
                    </p>
                    <p className="mt-6 font-arabic text-base text-ink/80">
                        {t('common.footer.ar_line')}
                    </p>
                </div>

                {NAV_COLUMNS.map((col) => (
                    <div key={col.titleKey} className="md:col-span-2">
                        <p className="mb-4 eyebrow">{t(col.titleKey)}</p>
                        <ul className="space-y-2.5 text-sm text-ink/80">
                            {col.links.map((link) => (
                                <li key={link.labelKey}>
                                    <Link
                                        href={link.href}
                                        className="transition-colors hover:text-ink"
                                    >
                                        {t(link.labelKey)}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                <div className="col-span-2 md:col-span-2">
                    <p className="mb-4 eyebrow">
                        {t('common.footer.follow.title')}
                    </p>
                    <ul className="space-y-2.5 text-sm text-ink/80">
                        <li>
                            <a
                                href="https://www.instagram.com/hirfaty.com_/"
                                target="_blank"
                                className="hover:text-ink"
                            >
                                Instagram
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://www.facebook.com/harfti"
                                target="_blank"
                                className="hover:text-ink"
                            >
                                Facebook
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://ma.linkedin.com/company/hirfaty"
                                target="_blank"
                                className="hover:text-ink"
                            >
                                Linkedin
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://www.youtube.com/@AjiTchoufHarfti"
                                target="_blank"
                                className="hover:text-ink"
                            >
                                Youtube
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="relative container border-t border-ink/10 py-10">
                <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div className="max-w-sm">
                        <p className="mb-3 eyebrow">
                            {t('common.footer.newsletter.title')}
                        </p>
                        <p className="text-sm leading-relaxed text-ink-soft">
                            {t('common.footer.newsletter.blurb')}
                        </p>
                    </div>

                    <NewsletterForm />
                </div>
            </div>

            <div className="container flex flex-col items-start justify-between gap-4 border-t border-ink/10 pt-6 pb-10 text-xs text-ink-soft md:flex-row md:items-center">
                {/* `t()` has no placeholder support — it is a flat key lookup
                    (see use-translation.tsx) — so `:year` is substituted here
                    rather than server-side. */}
                <p>
                    {t('common.footer.copyright').replace(
                        ':year',
                        String(new Date().getFullYear()),
                    )}
                </p>
                <p className="flex items-center gap-2">
                    <span className="inline-block h-1.5 w-1.5 rounded-full bg-terracotta" />
                    {t('common.footer.shipping')}
                </p>
            </div>
        </footer>
    );
};

export default SiteFooter;
