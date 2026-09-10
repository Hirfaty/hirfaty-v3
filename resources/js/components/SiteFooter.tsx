import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';

export const SiteFooter = () => {
    const { t } = useTranslation();

    const columns = [
        {
            title: t('footer.buyers_title'),
            links: [
                t('footer.buyers_explore'),
                t('footer.buyers_categories'),
                t('footer.buyers_sellers'),
                t('footer.buyers_how_it_works'),
            ],
        },
        {
            title: t('footer.sellers_title'),
            links: [
                t('footer.sellers_sell_on_hirfaty'),
                t('footer.sellers_pricing'),
                t('footer.sellers_help_center'),
                t('footer.sellers_stories'),
            ],
        },
        {
            title: t('footer.company_title'),
            links: [
                t('footer.company_about'),
                t('footer.company_contact'),
                t('footer.company_press'),
                t('footer.company_terms'),
            ],
        },
    ];

    return (
        <footer className="mt-32 border-t border-ink/15 bg-cream">
            <div className="container grid grid-cols-2 gap-10 py-16 md:grid-cols-12">
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
                        {t('footer.tagline')}
                    </p>
                    <p className="mt-6 font-arabic text-base text-ink/80">
                        {t('footer.tagline_arabic')}
                    </p>
                </div>

                {columns.map((col) => (
                    <div key={col.title} className="md:col-span-2">
                        <p className="mb-4 eyebrow">{col.title}</p>
                        <ul className="space-y-2.5 text-sm text-ink/80">
                            {col.links.map((l) => (
                                <li key={l}>
                                    <Link
                                        href="#"
                                        className="transition-colors hover:text-ink"
                                    >
                                        {l}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                <div className="col-span-2 md:col-span-2">
                    <p className="mb-4 eyebrow">{t('footer.follow_title')}</p>
                    <ul className="space-y-2.5 text-sm text-ink/80">
                        <li>
                            <a href="#" className="hover:text-ink">
                                {t('footer.follow_instagram')}
                            </a>
                        </li>
                        <li>
                            <a href="#" className="hover:text-ink">
                                {t('footer.follow_tiktok')}
                            </a>
                        </li>
                        <li>
                            <a href="#" className="hover:text-ink">
                                {t('footer.follow_youtube')}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="container flex flex-col items-start justify-between gap-4 border-t border-ink/10 pt-6 pb-10 text-xs text-ink-soft md:flex-row md:items-center">
                <p>
                    {t('footer.copyright').replace(
                        ':year',
                        String(new Date().getFullYear()),
                    )}
                </p>
                <p className="flex items-center gap-2">
                    <span className="inline-block h-1.5 w-1.5 rounded-full bg-terracotta" />
                    {t('footer.shipping_notice')}
                </p>
            </div>
        </footer>
    );
};

export default SiteFooter;
