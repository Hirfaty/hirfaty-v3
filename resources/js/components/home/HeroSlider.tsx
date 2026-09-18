import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useSnapCarousel } from '@/hooks/use-snap-carousel';

export type HeroSlide = {
    src: string;
    /** Already-translated alt text — this component does no lookups itself. */
    alt: string;
};

export default function HeroSlider({
    slides,
    interval = 5000,
    label,
    prevLabel,
    nextLabel,
    isRTL,
    children,
}: {
    slides: HeroSlide[];
    /** Milliseconds each slide stays before advancing. */
    interval?: number;
    /** Accessible name for the dot group, e.g. "Choose a photograph". */
    label: string;
    prevLabel: string;
    nextLabel: string;
    /**
     * Needed only to flip the sign of `scrollLeft` — see the scroll effect.
     * Passed in rather than read from `useTranslation` so this stays a dumb
     * presentational component, like the rail next to it on the page.
     */
    isRTL: boolean;
    /** The claim, centred over the photography. */
    children?: ReactNode;
}) {
    const { track, index, goTo, step, order, pauseProps } =
        useSnapCarousel<HTMLUListElement>(slides.length, interval, isRTL);

    if (slides.length === 0) {
        return null;
    }

    const arrow =
        'group absolute cursor-pointer top-1/2 z-30 grid h-11 w-11 -translate-y-1/2 rotate-45 place-items-center bg-souk-paper text-souk-brown duration-500  transition-colors hover:bg-souk-brown hover:text-souk-cream sm:h-14 sm:w-14';

    return (
        <div {...pauseProps} className="relative mx-auto max-w-384">
            <ul
                ref={track}
                className="flex h-[clamp(18rem,50svh,30rem)] snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {order.map((slide, position) => (
                    <li
                        // Positional, because `order` repeats a slide at each
                        // end — see `useSnapCarousel`.
                        key={position}
                        className="relative h-full w-full shrink-0 grow-0 basis-full snap-start snap-always"
                    >
                        <img
                            src={slides[slide].src}
                            // Only the resting slide carries its description:
                            // three alt texts announced in a row describe a
                            // gallery, not the one picture on show. Matched on
                            // position rather than on `slide` so the trailing
                            // clone of slide 0 stays silent too.
                            alt={position === index ? slides[slide].alt : ''}
                            // The first is the LCP candidate; the rest, the
                            // clone included, are offscreen until paged to.
                            loading={position === 0 ? 'eager' : 'lazy'}
                            className="h-full w-full object-cover"
                            draggable={false}
                        />
                    </li>
                ))}
            </ul>

            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-10"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-10 bg-gradient-to-t from-souk-title/70 via-transparent to-souk-title/45"
            />

            <div className="pointer-events-none absolute inset-0 z-20 flex items-center justify-center px-8 sm:px-14">
                <div className="w-full max-w-6xl text-center">{children}</div>
            </div>

            {slides.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={() => step(-1)}
                        aria-label={prevLabel}
                        className={`${arrow} -start-5 sm:-start-7`}
                    >
                        <ChevronLeft
                            className="h-5 w-5 -rotate-45 rtl:rotate-[135deg]"
                            strokeWidth={1.5}
                        />
                    </button>
                    <button
                        type="button"
                        onClick={() => step(1)}
                        aria-label={nextLabel}
                        className={`${arrow} -end-5 sm:-end-7`}
                    >
                        <ChevronRight
                            className="h-5 w-5 -rotate-45 rtl:rotate-[135deg]"
                            strokeWidth={1.5}
                        />
                    </button>

                    <div
                        role="group"
                        aria-label={label}
                        className="absolute inset-x-0 bottom-6 z-30 flex items-center justify-center gap-2.5 lg:bottom-8"
                    >
                        {slides.map((slide, i) => (
                            <button
                                key={slide.src}
                                type="button"
                                onClick={() => goTo(i)}
                                aria-label={slide.alt}
                                aria-current={i === index}
                                className={`h-1.5 rounded-full transition-all duration-500 ${
                                    i === index
                                        ? 'w-8 bg-souk-cream'
                                        : 'w-1.5 bg-white/45 hover:bg-white/75'
                                }`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
