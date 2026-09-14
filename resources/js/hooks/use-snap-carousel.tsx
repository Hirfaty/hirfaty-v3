import { useEffect, useRef } from 'react';
import { useSlideshow } from '@/hooks/use-slideshow';

/**
 * The scroll-snap-to-index animation behind `HeroSlider`, pulled out so other
 * horizontally swipable, auto-advancing rails (the category tiles and the
 * product spotlight) can share it instead of re-deriving the same tween,
 * scroll listener, and resize handling.
 *
 * Render the track from the returned `order` rather than from your own array:
 * with two or more slides it repeats the first slide once at the end. That one
 * clone is what lets a wrap keep travelling the way it was already going.
 * Without it, advancing off the end animates `scrollLeft` the whole way back to
 * zero, which reads as the rail rewinding past every slide in between rather
 * than as it looping round.
 *
 * One clone, at the end, rather than the symmetrical pair the trick is usually
 * built from. A copy of the *last* slide in front of the first would buy a
 * prettier backward wrap, but it also means `scrollLeft: 0` is no longer where
 * slide 0 lives — and a scroller gets reset to 0 by the browser more or less
 * whenever it feels like it (being revealed from `display: none`, most of all,
 * which is exactly what the category rail does at the `sm` breakpoint). Leaving
 * the natural resting position correct is worth more than the symmetry; the
 * backward wrap gets the same effect out of the trailing clone anyway, by
 * hopping onto it first — see the tween.
 */
export function useSnapCarousel<T extends HTMLElement>(
    count: number,
    interval: number,
    isRTL: boolean,
) {
    const track = useRef<T>(null);
    const { index, previous, goTo, reduced, pauseProps } = useSlideshow(
        count,
        interval,
    );

    /*
     * The in-flight travel animation, or null when the scroller is at rest.
     * Doubles as the "this movement is ours" flag the scroll listener reads to
     * tell a tween apart from a genuine swipe.
     *
     * A ref, not state: nothing renders from it, and the scroll listener has
     * to see it in the same frame it is written.
     */
    const animation = useRef<number | null>(null);

    /** The resting slide, for the resize handler — see its effect. */
    const settled = useRef(index);

    /** A slide is only worth cloning once there are two to move between. */
    const looped = count > 1;

    useEffect(() => {
        settled.current = index;
    }, [index]);

    useEffect(() => {
        const el = track.current;

        if (!el || !el.clientWidth) {
            return;
        }

        const width = el.clientWidth;
        const sign = isRTL ? -1 : 1;
        const at = (position: number) => position * width * sign;

        const resting = at(index);

        /*
         * Already parked where this index belongs, so there is nothing to
         * travel. This is the case whenever the reader swiped here themselves:
         * the scroll listener sets the index to match a position the track has
         * already reached, and without this the tween would fight them for it.
         */
        if (Math.abs(resting - el.scrollLeft) < 2) {
            return;
        }

        if (reduced) {
            el.scrollLeft = resting;

            return;
        }

        const forward = looped && previous === count - 1 && index === 0;
        const backward = looped && previous === 0 && index === count - 1;

        let from = el.scrollLeft;
        let target = resting;

        /**
         * Where to teleport to once the tween arrives, or null when it is
         * already heading somewhere real. Only a forward wrap needs one: it
         * finishes on the clone, which has to be swapped for the slide it
         * copies so there is track left to travel next time.
         */
        let landing: number | null = null;

        if (forward) {
            target = at(count);
            landing = at(0);
        } else if (backward) {
            /*
             * Hop onto the clone before setting off. It is a copy of slide 0,
             * which is where we already are, so nothing changes on screen —
             * but it moves us to the far end of the track, where the last
             * slide is the one immediately behind us rather than the whole
             * rail away.
             */
            el.scrollLeft = at(count);
            from = at(count);
            target = at(count - 1);
        }

        el.style.scrollSnapType = 'none';

        const distance = target - from;
        const duration = 620;
        const start = performance.now();

        /**
         * Whether any frame has actually moved the track yet, so the cleanup
         * can tell a tween it is cutting short from one that never began.
         */
        let travelled = false;

        const step = (now: number) => {
            const p = Math.min((now - start) / duration, 1);

            const eased = p < 0.5 ? 4 * p ** 3 : 1 - (-2 * p + 2) ** 3 / 2;

            el.scrollLeft = from + distance * eased;
            travelled = true;

            if (p < 1) {
                animation.current = requestAnimationFrame(step);

                return;
            }

            /*
             * Snapping goes back on *before* the closing position is written,
             * not after. Re-enabling it makes the browser re-snap to whatever
             * it still holds as the target — which, since the tween moved the
             * track with snapping off, is wherever the journey started. Writing
             * afterwards puts the correct slide under that re-snap, so the
             * nearest snap point is the one we actually want.
             */
            el.style.scrollSnapType = '';
            el.scrollLeft = landing ?? target;

            animation.current = null;
        };

        animation.current = requestAnimationFrame(step);

        // Runs on the next index change too, which is what abandons a tween
        // half-way when someone pages faster than the animation. A wrap cut
        // short is sent straight to its landing rather than left part-way onto
        // the clone, which the next tween would otherwise have to rewind all
        // the way back across.
        return () => {
            if (animation.current !== null) {
                cancelAnimationFrame(animation.current);
                animation.current = null;

                el.style.scrollSnapType = '';

                if (landing !== null && travelled) {
                    el.scrollLeft = landing;
                }

                return;
            }

            el.style.scrollSnapType = '';
        };
    }, [index, previous, isRTL, reduced, count, looped]);

    useEffect(() => {
        const el = track.current;

        if (!el) {
            return;
        }

        const onScroll = () => {
            if (animation.current !== null || !el.clientWidth) {
                return;
            }

            const width = el.clientWidth;
            const position = Math.round(Math.abs(el.scrollLeft) / width);

            /*
             * Swiped clean off the end and onto the clone. It shows slide 0, so
             * teleporting to the real one changes nothing on screen and leaves
             * the whole rail ahead to carry on swiping into.
             */
            if (looped && position >= count) {
                el.scrollLeft = 0;
                goTo(0);

                return;
            }

            if (position !== index) {
                goTo(position);
            }
        };

        el.addEventListener('scroll', onScroll, { passive: true });

        return () => el.removeEventListener('scroll', onScroll);
    }, [index, goTo, count, looped]);

    useEffect(() => {
        const el = track.current;

        if (!el || typeof ResizeObserver === 'undefined') {
            return;
        }

        let width = el.clientWidth;

        const observer = new ResizeObserver(() => {
            if (el.clientWidth === width) {
                return;
            }

            width = el.clientWidth;
            el.scrollLeft = settled.current * width * (isRTL ? -1 : 1);
        });

        observer.observe(el);

        return () => observer.disconnect();
    }, [isRTL]);

    const step = (delta: 1 | -1) => goTo((index + delta + count) % count);

    /**
     * Track positions to render, as indices into your own slide array: every
     * slide, then the first one again. Map over this rather than the slides
     * themselves, and key by position — the clone repeats a slide, so any key
     * derived from the slide itself would collide.
     */
    const order = looped
        ? [...Array.from({ length: count }, (_, i) => i), 0]
        : Array.from({ length: count }, (_, i) => i);

    return { track, index, goTo, step, order, reduced, pauseProps };
}

export default useSnapCarousel;
