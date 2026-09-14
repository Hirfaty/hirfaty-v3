import type { ReactNode } from 'react';
import { useSlideshow } from '@/hooks/use-slideshow';

export type HeroSlide = {
    src: string;
    /** Already-translated alt text — this component does no lookups itself. */
    alt: string;
};

type Props = {
    slides: HeroSlide[];
    /** Milliseconds each slide stays before advancing. */
    interval?: number;
    className?: string;
    /**
     * The hero copy, filling the card's other half beside the photography.
     * Taken as children rather than rendered by the caller alongside the
     * carousel because the dots have to sit *outside* the card's
     * `overflow-hidden`, so the card and the dots must be siblings owned by
     * this component.
     */
    children?: ReactNode;
};

/**
 * Auto-advancing crossfade carousel filling one half of the page hero card,
 * with the copy passed in as children for the other half.
 *
 * Hand-rolled rather than pulling in embla/swiper: this needs exactly one
 * behaviour (fade between N stills, advance on a timer), and a dependency
 * would cost more bytes than the whole component.
 *
 * Slides are stacked and crossfaded rather than translated, so nothing has to
 * know about RTL — a translating track would need its direction flipped for
 * Arabic.
 *
 * Motion rules live in `useSlideshow`, which the home page's `HeroSlider`
 * shares — see that hook for what pauses the timer and why.
 */
export default function HeroCarousel({
    slides,
    interval = 3000,
    className = '',
    children,
}: Props) {
    const {
        index,
        goTo,
        reduced: reducedMotion,
        pauseProps,
    } = useSlideshow(slides.length, interval);

    if (slides.length === 0) {
        return null;
    }

    return (
        <div className={className} {...pauseProps}>
            {/* Split in half rather than overlaying copy on photography: the
                type sits on a flat panel, so it needs no scrim and stays
                legible whichever slide is showing. Brown rather than peach —
                the listing is otherwise wall-to-wall light, and this is the
                one band big enough to balance it. */}
            <div className="grid overflow-hidden bg-souk-brown shadow-xl shadow-souk-brown/20 md:grid-cols-2">
                {/* Copy. First in the DOM, so it takes the start column and
                    follows RTL without any explicit ordering. */}
                <div>{children}</div>

                {/* Photography, filling its own half. Stretches to the row
                    height set by the copy on desktop (grid items stretch by
                    default); the min-height only matters once the columns
                    stack on mobile and there is no sibling to match.

                    `overflow-hidden` is load-bearing: the Ken Burns drift
                    scales the image past its box, and without clipping here
                    the only thing containing it is the card's own rounding —
                    so the zoom bled across the midpoint and over the copy. */}
                <div className="relative min-h-[15rem] overflow-hidden md:min-h-0">
                    {slides.map((slide, i) => {
                        const active = i === index;

                        return (
                            <div
                                key={slide.src}
                                aria-hidden={!active}
                                className={`absolute inset-0 transition-opacity duration-[1200ms] ease-[cubic-bezier(0.4,0,0.2,1)] ${
                                    active ? 'opacity-100' : 'opacity-0'
                                }`}
                            >
                                {/* Dropping the animation class when the slide
                                    goes inactive resets the drift, so it
                                    replays from the start next time round
                                    rather than resuming at the end of its
                                    travel. */}
                                <img
                                    src={slide.src}
                                    alt={active ? slide.alt : ''}
                                    // The first slide is the LCP candidate;
                                    // the rest are offscreen until their turn.
                                    loading={i === 0 ? 'eager' : 'lazy'}
                                    className={`h-full w-full object-cover ${
                                        active && !reducedMotion
                                            ? 'animate-ken-burns'
                                            : ''
                                    }`}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>

            {slides.length > 1 && (
                <div className="mt-5 flex items-center justify-center gap-2.5">
                    {slides.map((slide, i) => {
                        const active = i === index;

                        return (
                            <button
                                key={slide.src}
                                type="button"
                                onClick={() => goTo(i)}
                                aria-label={slide.alt}
                                aria-current={active}
                                className={`h-2 w-2 rounded-full transition-colors duration-300 ${
                                    active
                                        ? 'bg-white'
                                        : 'bg-white/25 hover:bg-white/45'
                                }`}
                            />
                        );
                    })}
                </div>
            )}
        </div>
    );
}
