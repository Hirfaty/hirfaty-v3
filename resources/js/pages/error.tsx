import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Home } from 'lucide-react';
import { ZellijStarIcon } from '@/components/home/CraftIcons';
import SiteFooter from '@/components/SiteFooter';
import SiteHeader from '@/components/SiteHeader';
import { useTranslation } from '@/hooks/use-translation';

/** Museum-caption label, matching the rest of the storefront. */
const EYEBROW = 'text-[11px] tracking-[0.18em] uppercase';

/**
 * The statuses bootstrap/app.php routes here. Anything else that somehow
 * reaches this component falls back to the 404 copy rather than rendering a
 * bare key — a page whose whole job is to explain a failure should never
 * itself look broken.
 */
const HANDLED = [403, 404, 500, 503] as const;

type Handled = (typeof HANDLED)[number];

export default function ErrorPage({ status }: { status: number }) {
    const { t } = useTranslation();

    const code: Handled = (HANDLED as readonly number[]).includes(status)
        ? (status as Handled)
        : 404;

    const title = t(`common.errors.${code}.title`);
    const body = t(`common.errors.${code}.body`);

    return (
        <div className="min-h-screen bg-souk-paper">
            {/*
             * Title is set here rather than left to the Blade root: the root
             * renders the bare app name for every page, so without this a 404
             * shows up in history and in the tab as an ordinary page.
             */}
            <Head title={`${code} — ${title}`} />

            <SiteHeader />

            <section className="relative overflow-hidden">
                {/*
                 * Zellij lattice behind the message. Tuned faint, as in the
                 * header: at section strength it reads as graph paper behind
                 * the text rather than as texture.
                 */}
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
                    <ZellijStarIcon className="h-10 w-10 text-souk-brown/30" />

                    <p className={`mt-8 text-souk-brown ${EYEBROW}`}>
                        {t('common.errors.eyebrow').replace(
                            ':status',
                            String(code),
                        )}
                    </p>

                    {/*
                     * The number itself, oversized. `tabular-nums` so 404 and
                     * 503 occupy the same width and the layout doesn't shift
                     * between statuses.
                     */}
                    <p className="mt-3 font-display text-[clamp(5rem,18vw,11rem)] leading-[0.85] tracking-[-0.03em] text-souk-title tabular-nums">
                        {code}
                    </p>

                    <h1 className="mt-6 max-w-xl font-display text-[clamp(1.5rem,3vw,2.2rem)] leading-[1.15] tracking-[-0.01em] text-souk-title">
                        {title}
                    </h1>

                    <p className="mt-4 max-w-md text-[15px] leading-relaxed text-souk-brown-deep/75">
                        {body}
                    </p>

                    <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                        <Link
                            href={route('home')}
                            className="inline-flex items-center justify-center gap-2.5 rounded-xl bg-souk-brown px-6 py-3.5 text-sm font-medium text-souk-cream transition-colors hover:bg-souk-brown-deep"
                        >
                            <Home className="h-4 w-4" strokeWidth={1.6} />
                            {t('common.errors.back_home')}
                        </Link>

                        <Link
                            href={route('products.index')}
                            className="group inline-flex items-center justify-center gap-2.5 rounded-xl border border-souk-brown/25 px-6 py-3.5 text-sm font-medium text-souk-brown-deep transition-colors hover:border-souk-brown hover:text-souk-brown"
                        >
                            {t('common.errors.browse_market')}
                            <ArrowRight
                                className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1"
                                strokeWidth={1.6}
                            />
                        </Link>
                    </div>
                </div>
            </section>

            <SiteFooter />
        </div>
    );
}
