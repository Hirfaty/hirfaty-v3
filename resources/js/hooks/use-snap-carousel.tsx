import { useEffect, useRef } from 'react';
import { useSlideshow } from '@/hooks/use-slideshow';

/**
 * The scroll-snap-to-index animation behind `HeroSlider`, pulled out so other
 * horizontally swipable, auto-advancing rails (the category tiles and product
 * spotlight on mobile) can share it instead of re-deriving the same tween,
 * scroll listener, and resize handling.
 */
export function useSnapCarousel<T extends HTMLElement>(
    count: number,
    interval: number,
    isRTL: boolean,
) {
    const track = useRef<T>(null);
    const { index, goTo, reduced, pauseProps } = useSlideshow(count, interval);

    /*
     * The in-flight travel animation, or null when the scroller is at rest.
     * Doubles as the "this movement is ours" flag the scroll listener reads to
     * tell a tween apart from a genuine swipe.
     */
    const animation = useRef<number | null>(null);

    /** The resting slide, for the resize handler — see its effect. */
    const settled = useRef(index);

    useEffect(() => {
        settled.current = index;
    }, [index]);

    useEffect(() => {
        const el = track.current;

        if (!el || !el.clientWidth) {
            return;
        }

        const from = el.scrollLeft;
        const target = index * el.clientWidth * (isRTL ? -1 : 1);
        const distance = target - from;

        if (Math.abs(distance) < 2) {
            return;
        }

        if (reduced) {
            el.scrollLeft = target;

            return;
        }

        el.style.scrollSnapType = 'none';

        const duration = 620;
        const start = performance.now();

        const step = (now: number) => {
            const p = Math.min((now - start) / duration, 1);

            const eased = p < 0.5 ? 4 * p ** 3 : 1 - (-2 * p + 2) ** 3 / 2;

            el.scrollLeft = from + distance * eased;

            if (p < 1) {
                animation.current = requestAnimationFrame(step);

                return;
            }

            animation.current = null;
            el.style.scrollSnapType = '';
        };

        animation.current = requestAnimationFrame(step);

        return () => {
            if (animation.current !== null) {
                cancelAnimationFrame(animation.current);
                animation.current = null;
            }

            el.style.scrollSnapType = '';
        };
    }, [index, isRTL, reduced]);

    useEffect(() => {
        const el = track.current;

        if (!el) {
            return;
        }

        const onScroll = () => {
            if (animation.current !== null || !el.clientWidth) {
                return;
            }

            const at = Math.round(Math.abs(el.scrollLeft) / el.clientWidth);

            if (at !== index) {
                goTo(at);
            }
        };

        el.addEventListener('scroll', onScroll, { passive: true });

        return () => el.removeEventListener('scroll', onScroll);
    }, [index, goTo]);

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

    return { track, index, goTo, step, reduced, pauseProps };
}

export default useSnapCarousel;
