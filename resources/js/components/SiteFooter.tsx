import { Link } from '@inertiajs/react';
import VaseIcon from '@/components/home/VaseIcon';
import logo from '@/images/logo.png';

export const SiteFooter = () => {
    return (
        <footer className="relative mt-32 overflow-hidden border-t border-ink/15 bg-cream">
            {/* Watermark, not an illustration: faint enough to read as paper
                texture rather than compete with the columns above it, and
                clipped by the footer's own edges so it never leaks into the
                page above. `pointer-events-none` keeps it out of the way of
                the links sitting over it. */}
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
                        La place de marché de l'artisanat marocain. Des objets
                        faits à la main, par des artisans de Fès, Marrakech,
                        Chefchaouen et au-delà.
                    </p>
                    <p className="mt-6 font-arabic text-base text-ink/80">
                        حرفتي — صنع بفخر في المغرب
                    </p>
                </div>

                {[
                    {
                        title: 'Acheteurs',
                        links: [
                            'Explorer',
                            'Catégories',
                            'Vendeurs',
                            'Comment ça marche',
                        ],
                    },
                    {
                        title: 'Artisans',
                        links: [
                            'Vendre sur Hirfaty',
                            'Tarifs',
                            "Centre d'aide vendeur",
                            "Histoires d'artisans",
                        ],
                    },
                    {
                        title: 'Société',
                        links: ['À propos', 'Contact', 'Presse', 'Conditions'],
                    },
                ].map((col) => (
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
                    <p className="mb-4 eyebrow">Suivre</p>
                    <ul className="space-y-2.5 text-sm text-ink/80">
                        <li>
                            <a href="#" className="hover:text-ink">
                                Instagram
                            </a>
                        </li>
                        <li>
                            <a href="#" className="hover:text-ink">
                                TikTok
                            </a>
                        </li>
                        <li>
                            <a href="#" className="hover:text-ink">
                                YouTube
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="container flex flex-col items-start justify-between gap-4 border-t border-ink/10 pt-6 pb-10 text-xs text-ink-soft md:flex-row md:items-center">
                <p>
                    © {new Date().getFullYear()} Hirfaty.com — Tous droits
                    réservés.
                </p>
                <p className="flex items-center gap-2">
                    <span className="inline-block h-1.5 w-1.5 rounded-full bg-terracotta" />
                    Livraison dans tout le Maroc.
                </p>
            </div>
        </footer>
    );
};

export default SiteFooter;
