import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';

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
 * Motion rules:
 *  - `prefers-reduced-motion` disables both the auto-advance and the Ken
 *    Burns drift. The first slide is shown statically and the controls still
 *    work, so the content is never withheld from anyone.
 *  - The timer pauses on hover and on keyboard focus, so the image can't
 *    change out from under someone reading or tabbing through the controls.
 *  - It also pauses while the tab is hidden, so a backgrounded tab doesn't
 *    burn a timer and animation frames for nobody.
 */
export default function HeroCarousel({
    slides,
    interval = 3500,
    className = '',
    children,
}: Props) {
    const [index, setIndex] = useState(0);

    // Seeded from the current visibility rather than plain `false`: the
    // listener below only fires on *change*, so a page opened in a background
    // tab would otherwise run the timer and the Ken Burns animation until the
    // tab was first focused.
    const [paused, setPaused] = useState(
        () => typeof document !== 'undefined' && document.hidden,
    );

    // Resolved at first render, not in an effect: starting the timer and then
    // tearing it down would already have moved the slide for someone who asked
    // for no motion.
    const [reducedMotion] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    // Held in a ref so restarting the timer (on manual navigation) doesn't
    // depend on `index` being in the effect's dependency list, which would
    // re-create the interval on every automatic tick too.
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (reducedMotion || paused || slides.length < 2) {
            return;
        }

        timer.current = setInterval(
            () => setIndex((i) => (i + 1) % slides.length),
            interval,
        );

        return () => {
            if (timer.current) {
                clearInterval(timer.current);
            }
        };
    }, [reducedMotion, paused, slides.length, interval]);

    useEffect(() => {
        const onVisibility = () => setPaused(document.hidden);

        document.addEventListener('visibilitychange', onVisibility);

        return () =>
            document.removeEventListener('visibilitychange', onVisibility);
    }, []);

    if (slides.length === 0) {
        return null;
    }

    return (
        <div
            className={className}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocusCapture={() => setPaused(true)}
            onBlurCapture={() => setPaused(false)}
        >
            {/* Split in half rather than overlaying copy on photography: the
                type sits on flat cream, so it needs no scrim and stays
                legible whichever slide is showing. */}
            <div className="grid overflow-hidden bg-souk-peach shadow-xl shadow-souk-brown/10 md:grid-cols-2">
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
                                onClick={() => setIndex(i)}
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
