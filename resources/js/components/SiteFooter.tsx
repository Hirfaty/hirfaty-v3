import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';

export const SiteFooter = () => {
    const { t } = useTranslation();

    const columns = [
        {
            title: t('common.footer.buyers'),
            links: [
                { label: t('common.footer.explore'), href: '/products' },
                { label: t('common.footer.categories'), href: '/products' },
                { label: t('common.footer.sellers'), href: '#' },
                { label: t('common.footer.how_it_works'), href: '/#' },
            ],
        },
        {
            title: t('common.footer.artisans'),
            links: [
                {
                    label: t('common.footer.sell_on'),
                    href: '/register?type=seller',
                },
                { label: t('common.footer.pricing'), href: '#' },
                { label: t('common.footer.seller_help'), href: '#' },
                { label: t('common.footer.stories'), href: '#' },
            ],
        },
        {
            title: t('common.footer.company'),
            links: [
                { label: t('common.footer.about'), href: '/about' },
                { label: t('common.footer.contact'), href: '#' },
                { label: t('common.footer.press'), href: '#' },
                { label: t('common.footer.terms'), href: '/terms' },
                { label: t('common.footer.privacy'), href: '/privacy' },
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
                            alt={t('common.app_name')}
                            className="h-8 w-8 object-contain"
                        />
                        <span className="font-display text-lg text-ink">
                            Hirfaty<span className="text-terracotta">.</span>
                        </span>
                    </div>
                    <p className="mt-5 max-w-xs text-sm leading-relaxed text-ink-soft">
                        {t('common.footer.tagline')}
                    </p>
                    <p className="mt-6 font-arabic text-base text-ink/80">
                        {t('common.footer.made_in')}
                    </p>
                </div>

                {columns.map((col) => (
                    <div key={col.title} className="md:col-span-2">
                        <p className="eyebrow mb-4">{col.title}</p>
                        <ul className="space-y-2.5 text-sm text-ink/80">
                            {col.links.map((l) => (
                                <li key={l.label}>
                                    <Link
                                        href={l.href}
                                        className="transition-colors hover:text-ink"
                                    >
                                        {l.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                <div className="col-span-2 md:col-span-2">
                    <p className="eyebrow mb-4">{t('common.footer.follow')}</p>
                    <ul className="space-y-2.5 text-sm text-ink/80">
                        {['Instagram', 'TikTok', 'YouTube'].map((s) => (
                            <li key={s}>
                                <a
                                    href="#"
                                    className="transition-colors hover:text-ink"
                                >
                                    {s}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <div className="container flex flex-col items-start justify-between gap-4 border-t border-ink/10 pt-6 pb-10 text-xs text-ink-soft md:flex-row md:items-center">
                <p>
                    © {new Date().getFullYear()} Hirfaty.com —{' '}
                    {t('common.footer.rights')}
                </p>
                <p className="flex items-center gap-2">
                    <span className="inline-block h-1.5 w-1.5 rounded-full bg-terracotta" />
                    {/* ":carrier" is highlighted rather than inlined as plain text. */}
                    {t('common.footer.delivery')
                        .split(':carrier')
                        .flatMap((part, i) =>
                            i === 0
                                ? [part]
                                : [
                                      <span
                                          key="carrier"
                                          className="font-medium text-ink"
                                      >
                                          Amana
                                      </span>,
                                      part,
                                  ],
                        )}
                </p>
            </div>
        </footer>
    );
};

export default SiteFooter;
