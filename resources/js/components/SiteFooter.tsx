import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import VaseIcon from '@/components/home/VaseIcon';
import { useTranslation } from '@/hooks/use-translation';
import logo from '@/images/logo.png';
import newsletter from '@/routes/newsletter';

/**
 * Mailing-list signup.
 *
 * `errorBag` matters more than it looks: this form is in the footer of every
 * page, and Inertia hands a page one flat `errors` object. Without a bag of its
 * own, a rejected address here would surface on the `email` field of whatever
 * other form shares the screen — the login box, checkout, seller settings. The
 * server puts them in the same bag; see `NewsletterSubscriptionRequest`.
 *
 * Confirmation is raised here rather than left to `useFlashToast`, which does
 * not do what its name suggests: it listens for a `flash.toast` payload that
 * nothing in this codebase ever sends, so it never fires. Every other place
 * that wants a toast raises it in its own `onSuccess` — see
 * `pages/admin/products/index.tsx` — and this follows suit. The controller
 * still flashes `success`, which is the server-side convention and is what is
 * read below when present.
 */
function NewsletterForm() {
    const { t } = useTranslation();
    const page = usePage<{ flash?: { success?: string } }>();
    const form = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(newsletter.store.url(), {
            errorBag: 'newsletter',
            // The form is at the very bottom of a long page; without this,
            // confirming a signup throws the reader back up to the top of it.
            preserveScroll: true,
            onSuccess: () => {
                form.reset('email');
                // Falls back to the local translation so the confirmation
                // survives the flash going missing for any reason.
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

export const SiteFooter = () => {
    const { t } = useTranslation();

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
                        {t('common.footer.ar_line')}
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

            {/* Its own band rather than a thirteenth column: the grid above is
                a full 12 (4 + 2 + 2 + 2 + 2) and the signup needs a line of
                input to be usable at all, which no 2-wide column can give it. */}
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
