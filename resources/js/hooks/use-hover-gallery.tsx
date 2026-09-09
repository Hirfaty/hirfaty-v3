import { useEffect, useRef, useState } from 'react';

/**
 * Cycles a product tile through its photos while the pointer is over it —
 * the little "flip through the gallery" a shopper gets on Amazon/Etsy before
 * they ever open the product page.
 *
 * Deliberately not built on `useSlideshow` (the hero's hook): that one
 * *pauses* on hover, because there the cycling is the page's idle state and
 * hover means "someone is reading this, don't move it." Here hover is the
 * trigger, not the interruption — the opposite relationship — and a small
 * card thumbnail has no manual dots to drive, so the shared surface between
 * the two hooks would have been the reduced-motion/hidden-tab plumbing only.
 *
 * Resets to the first image on pointer-leave rather than freezing wherever it
 * stopped, so a second hover always replays the same sequence from the cover
 * shot — the one image chosen to represent the product everywhere else it is
 * shown un-hovered.
 */
export function useHoverGallery(count: number, interval = 900) {
    const [hovering, setHovering] = useState(false);

    /*
     * Current and outgoing image held as one value, as in `useSlideshow`, so
     * a consumer stacking them to crossfade can never read one updated
     * without the other and flash through to whatever sits behind the stack.
     */
    const [slide, setSlide] = useState({ current: 0, previous: 0 });

    const [reduced] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!hovering || reduced || count < 2) {
            return;
        }

        timer.current = setInterval(
            () =>
                setSlide((s) => ({
                    current: (s.current + 1) % count,
                    previous: s.current,
                })),
            interval,
        );

        return () => {
            if (timer.current) {
                clearInterval(timer.current);
            }
        };
    }, [hovering, reduced, count, interval]);

    // Pauses the visible frame at the cover shot while the tab is
    // backgrounded, rather than silently racing through the sequence behind
    // the user's back and landing on an arbitrary frame by the time they
    // return — matches the still-hovered cursor position doing nothing useful
    // while it can't be seen. `setState` calls here respond directly to the
    // browser event the listener exists for, not to synchronizing React with
    // an external system, so this isn't the pattern the "no setState in an
    // effect body" rule is guarding against — only the subscription itself
    // needs the effect.
    useEffect(() => {
        const onVisibility = () => {
            if (document.hidden) {
                setHovering(false);
                setSlide({ current: 0, previous: 0 });
            }
        };

        document.addEventListener('visibilitychange', onVisibility);

        return () =>
            document.removeEventListener('visibilitychange', onVisibility);
    }, []);

    return {
        index: slide.current < count ? slide.current : 0,
        previous: slide.previous < count ? slide.previous : 0,
        hoverProps: {
            onMouseEnter: () => setHovering(true),
            // Same direct reset as the visibility handler above: a pointer
            // leaving the tile is an event to respond to, not state to
            // synchronize, so it belongs in the handler rather than behind a
            // `hovering`-keyed effect.
            onMouseLeave: () => {
                setHovering(false);
                setSlide({ current: 0, previous: 0 });
            },
        },
    };
}

export default useHoverGallery;
