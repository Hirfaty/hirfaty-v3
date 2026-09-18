import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import type { ShowcaseProduct } from '@/components/home/ProductShowcase';
import { GridRail } from '@/components/home/ProductShowcase';
import Reveal from '@/components/home/Reveal';
import { useSnapCarousel } from '@/hooks/use-snap-carousel';
import { useTranslation } from '@/hooks/use-translation';

/**
 * How many products make up one slide of this row.
 *
 * Its own width rather than the showcase's `BAND_SIZE` — this row stands alone
 * with a full container to itself, where the showcase's rows share theirs with
 * the 30%-wide spotlight, so a wider slide reads right here without the
 * cards this shallow it would take to fit six beside the spotlight too.
 */
const SLIDE_SIZE = 6;

/** Six per slide, `sm` up; two on a phone, where six across would be stamps. */
const COLUMNS = 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6';

/**
 * How long the row holds before sliding on.
 *
 * Its own beat again, distinct from the spotlight's 4500ms and the showcase
 * rows' 5200ms — three sections on one clock would periodically fire together
 * and the whole page would lurch. See `BAND_INTERVAL` in `ProductShowcase`.
 */
const INTERVAL = 6100;

/**
 * "New arrivals": one row of six, scrolling through the eighteen most
 * recently listed pieces.
 *
 * The same rail the showcase's rows are built from, so a card here behaves
 * exactly as a card there — swipable by hand, tweened by the arrows, advancing
 * on its own. Two differences: `fill={false}`, because this row stands on its
 * own rather than dividing up a fixed screenful, so the cards keep their 4:5
 * shape instead of stretching into a height that does not exist; and
 * `bandSize`/`columnsClassName`, because six across needs both a wider slice
 * of the pool and its own grid split, not the showcase's four.
 */
export default function NewArrivals({
    products = [],
}: {
    products?: ShowcaseProduct[];
}) {
    const { t, isRTL } = useTranslation();

    /*
     * Floored, so a trailing partial slide never leaves gaps in the row — the
     * same call `ProductShowcase` makes about its own bands. Eighteen products
     * give three slides of six.
     */
    const slides = Math.floor(products.length / SLIDE_SIZE);

    /*
     * One slide is the whole row already; there is nothing to scroll to and
     * nothing for the arrows to do. `useSnapCarousel` stops its own timer below
     * two, so this also leaves the rail still.
     */
    const rail = useSnapCarousel<HTMLUListElement>(
        Math.max(slides, 1),
        INTERVAL,
        isRTL,
    );

    // Nothing to show rather than an empty band of placeholders: until six
    // pieces are listed, the section simply is not there.
    if (slides < 1) {
        return null;
    }

    return (
        <section className="bg-souk-paper py-6 lg:py-8">
            <div className="container">
                <Reveal delay={80}>
                    <h2 className="text-center font-display text-[clamp(2rem,4vw,1.5rem)] leading-[1.04] font-bold tracking-[-0.02em] text-souk-title">
                        {t('welcome.new_arrivals.heading')}
                    </h2>
                </Reveal>

                <Reveal delay={160} className="mt-6 lg:mt-8">
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Link
                                href="/new-arrivals"
                                className="group inline-flex items-center gap-2 border-b border-souk-brown/40 pb-1 text-sm text-souk-brown transition-colors hover:border-souk-brown"
                            >
                                {t('welcome.new_arrivals.view_all')}
                                <ArrowRight
                                    className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1"
                                    strokeWidth={1.75}
                                />
                            </Link>

                            {slides > 1 && (
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => rail.step(-1)}
                                        aria-label={t(
                                            'welcome.new_arrivals.prev',
                                        )}
                                        className="grid h-9 w-9 shrink-0 cursor-pointer place-items-center rounded-t-full rounded-b-md text-souk-brown ring-1 ring-souk-brown/25 transition-colors hover:bg-souk-brown hover:text-souk-cream"
                                    >
                                        <ChevronLeft
                                            className="h-4 w-4 rtl:rotate-180"
                                            strokeWidth={1.75}
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => rail.step(1)}
                                        aria-label={t(
                                            'welcome.new_arrivals.next',
                                        )}
                                        className="grid h-9 w-9 shrink-0 cursor-pointer place-items-center rounded-t-full rounded-b-md text-souk-brown ring-1 ring-souk-brown/25 transition-colors hover:bg-souk-brown hover:text-souk-cream"
                                    >
                                        <ChevronRight
                                            className="h-4 w-4 rtl:rotate-180"
                                            strokeWidth={1.75}
                                        />
                                    </button>
                                </div>
                            )}
                        </div>

                        {/*
                         * Hovering or tabbing into the row holds it, so a card
                         * cannot slide out from under someone reading it.
                         */}
                        <div {...rail.pauseProps}>
                            <GridRail
                                products={products}
                                bandCount={slides}
                                offset={0}
                                slides={slides}
                                track={rail.track}
                                order={rail.order}
                                currency={t('welcome.products.currency')}
                                fill={false}
                                bandSize={SLIDE_SIZE}
                                columnsClassName={COLUMNS}
                            />
                        </div>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
