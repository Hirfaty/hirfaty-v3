import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * The advance timer behind the site's crossfading image stacks.
 *
 * Extracted rather than written twice: the /products hero card and the home
 * page's full-bleed backdrop look nothing alike, but the rules about *when*
 * a slide may change are identical and are the fiddly part.
 *
 *  - `prefers-reduced-motion` disables the auto-advance outright. `reduced`
 *    is returned so a caller can also drop its Ken Burns drift; the controls
 *    keep working, so content is never withheld from anyone.
 *  - The timer pauses on hover and on keyboard focus (via the handlers
 *    returned in `pauseProps`), so an image cannot change out from under
 *    someone reading or tabbing through.
 *  - It also pauses while the tab is hidden, so a backgrounded tab does not
 *    burn a timer and animation frames for nobody.
 */
export function useSlideshow(count: number, interval = 3500) {
    /*
     * Current and outgoing slide held as one value rather than two pieces of
     * state, so they can never be read half-updated: a consumer that stacks
     * the outgoing slide under the incoming one to dissolve between them
     * would flash the page background for a frame if `previous` lagged
     * `current` by a render.
     */
    const [slide, setSlide] = useState({ current: 0, previous: 0 });
    const index = slide.current;

    // Seeded from the current visibility rather than plain `false`: the
    // listener below only fires on *change*, so a page opened in a background
    // tab would otherwise run the timer until the tab was first focused.
    const [paused, setPaused] = useState(
        () => typeof document !== 'undefined' && document.hidden,
    );

    // Resolved at first render, not in an effect: starting the timer and then
    // tearing it down would already have moved the slide for someone who
    // asked for no motion.
    const [reduced] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    // Held in a ref so restarting the timer on manual navigation doesn't
    // depend on `index` being in the effect's dependency list, which would
    // re-create the interval on every automatic tick too.
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (reduced || paused || count < 2) {
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
    }, [reduced, paused, count, interval]);

    useEffect(() => {
        const onVisibility = () => setPaused(document.hidden);

        document.addEventListener('visibilitychange', onVisibility);

        return () =>
            document.removeEventListener('visibilitychange', onVisibility);
    }, []);

    /**
     * Clamped rather than modulo'd: `count` can shrink between renders, and a
     * stale index past the end would blank the stack.
     */
    const goTo = useCallback(
        (next: number) =>
            setSlide((s) => ({
                current: Math.max(0, Math.min(next, count - 1)),
                previous: s.current,
            })),
        [count],
    );

    return {
        index: index < count ? index : 0,
        /**
         * The slide being replaced. Kept visible underneath the incoming one
         * so a crossfade never dips through to whatever is behind the stack.
         */
        previous: slide.previous < count ? slide.previous : 0,
        goTo,
        reduced,
        /** Spread onto the element whose hover/focus should hold the timer. */
        pauseProps: {
            onMouseEnter: () => setPaused(true),
            onMouseLeave: () => setPaused(false),
            onFocusCapture: () => setPaused(true),
            onBlurCapture: () => setPaused(false),
        },
    };
}

export default useSlideshow;
