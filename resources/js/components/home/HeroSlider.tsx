import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import { useSlideshow } from '@/hooks/use-slideshow';

export type HeroSlide = {
    src: string;
    /** Already-translated alt text — this component does no lookups itself. */
    alt: string;
};

/**
 * The home hero: a band of photography that *slides* sideways between stills,
 * inset from the page edges so the paper shows as a margin around it, with the
 * claim centred on top.
 *
 * Real overflow scrolling rather than a CSS transform on a track, for the same
 * reason `ProductRail` does it that way: a native scroller gets touch swipe,
 * trackpad flick, keyboard paging and scroll-snap for free, and the arrows and
 * the advance timer then just nudge `scrollLeft` like any other input. A
 * transform-driven track would have to re-implement all of that.
 *
 * Sliding is also why this replaced the old crossfading `HeroBackdrop`: a
 * dissolve has no direction, so it needed no RTL handling, whereas horizontal
 * travel does. That is handled once, in the sign of the scroll offset below —
 * the *layout* mirrors itself, because a flex row in a `dir="rtl"` document
 * already runs right-to-left, so slide 2 sits to the left of slide 1 without
 * being told to.
 *
 * Timer rules (what pauses the auto-advance, and reduced motion) live in
 * `useSlideshow`, shared with the site's other image stacks.
 */
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
    const track = useRef<HTMLUListElement>(null);
    const { index, goTo, reduced, pauseProps } = useSlideshow(
        slides.length,
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

    useEffect(() => {
        settled.current = index;
    }, [index]);

    /**
     * Drive the scroller from `index`, so the arrows, the dots and the timer
     * all share one source of truth and cannot disagree about which slide is
     * showing.
     *
     * The travel is tweened by hand rather than handed to
     * `scrollTo({ behavior: 'smooth' })`, which sounds like exactly the right
     * tool and is not: a smooth programmatic scroll is abandoned by the
     * browser whenever the snap container re-snaps, and this one re-snaps on
     * every index change, because the slides' `alt` text changes with it. The
     * scroll would silently never arrive. `ProductRail` drives its own drift
     * off `scrollLeft` frame by frame for the same class of reason.
     *
     * Snapping is suspended for the duration: the browser's snap-back and a
     * per-frame `scrollLeft` write otherwise pull against each other and the
     * band stutters. It is restored at the end, on an exact snap point, so
     * nothing moves when it comes back.
     *
     * `scrollLeft` counts *down* from 0 in a right-to-left container in every
     * current browser, hence the sign — the same flip `ProductRail` applies.
     */
    useEffect(() => {
        const el = track.current;

        if (!el || !el.clientWidth) {
            return;
        }

        const from = el.scrollLeft;
        const target = index * el.clientWidth * (isRTL ? -1 : 1);
        const distance = target - from;

        // Already there: the opening render, or a swipe that has just reported
        // its own resting slide back to us and would otherwise be re-scrolled
        // to where it already is.
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
            // easeInOutCubic — the band should pull away and settle rather
            // than start and stop at full speed.
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

        // Runs on the next index change too, which is what abandons a tween
        // half-way when someone pages faster than the animation: the new one
        // simply starts from wherever this one had got to.
        return () => {
            if (animation.current !== null) {
                cancelAnimationFrame(animation.current);
                animation.current = null;
            }

            el.style.scrollSnapType = '';
        };
    }, [index, isRTL, reduced]);

    /**
     * ...and the reverse: a swipe or a wheel nudge moves the photography
     * without going through `goTo`, so read the resting slide back out of the
     * scroll position to keep the dots and the timer honest.
     */
    useEffect(() => {
        const el = track.current;

        if (!el) {
            return;
        }

        const onScroll = () => {
            // Our own tween reports every intermediate position; only a
            // gesture is news.
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

    /*
     * Re-seat the current slide when the band is resized: slide offsets are
     * multiples of the track's own width, so a viewport change (or an
     * orientation flip) leaves the scroll position pointing between two
     * pictures. Scroll-snap does not fix this on its own — it acts on
     * gestures, not on relayout.
     *
     * Set up once and reading the slide from a ref, rather than keyed on
     * `index`: `observe()` always delivers one initial observation, so an
     * observer torn down and rebuilt on every index change would fire a
     * spurious re-seat in the middle of each tween and cut it short.
     */
    useEffect(() => {
        const el = track.current;

        if (!el || typeof ResizeObserver === 'undefined') {
            return;
        }

        let width = el.clientWidth;

        const observer = new ResizeObserver(() => {
            // Includes the initial observation, where the width by definition
            // has not changed yet.
            if (el.clientWidth === width) {
                return;
            }

            width = el.clientWidth;
            el.scrollLeft = settled.current * width * (isRTL ? -1 : 1);
        });

        observer.observe(el);

        return () => observer.disconnect();
    }, [isRTL]);

    if (slides.length === 0) {
        return null;
    }

    const step = (delta: 1 | -1) =>
        // Wraps rather than stopping at the ends: the band carries no
        // "you have reached the last picture" affordance, so a dead arrow
        // would just look broken.
        goTo((index + delta + slides.length) % slides.length);

    /*
     * A diamond straddling the edge of the photography — a rotated square with
     * the glyph counter-rotated back upright inside it, rather than the round
     * buttons the rail uses. Half of it overhangs into the page margin, which
     * is what the margin is for.
     *
     * `start`/`end` rather than left/right so the pair mirrors with the
     * document, and the chevrons take `rtl:rotate-180` because a chevron is a
     * direction, not an ornament.
     */
    const arrow =
        'group absolute cursor-pointer top-1/2 z-30 grid h-11 w-11 -translate-y-1/2 rotate-45 place-items-center bg-souk-paper text-souk-brown duration-500  transition-colors hover:bg-souk-brown hover:text-souk-cream sm:h-14 sm:w-14';

    return (
        <div {...pauseProps} className="relative">
            {/*
             * `snap-mandatory` with each slide exactly one track wide: the
             * band shows one photograph at a time and a half-way rest would
             * read as a fault rather than as a peek at the next one.
             *
             * The scrollbar is hidden because the arrows and the dots already
             * say the band is pageable, and a horizontal bar under a
             * full-width photograph reads as a layout bug.
             */}
            <ul
                ref={track}
                className="flex h-[clamp(24rem,72svh,42rem)] snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {slides.map((slide, i) => (
                    <li
                        key={slide.src}
                        className="relative h-full w-full shrink-0 grow-0 basis-full snap-start snap-always"
                    >
                        <img
                            src={slide.src}
                            // Only the resting slide carries its description:
                            // three alt texts announced in a row describe a
                            // gallery, not the one picture on show.
                            alt={i === index ? slide.alt : ''}
                            // The first is the LCP candidate; the rest are
                            // offscreen until paged to.
                            loading={i === 0 ? 'eager' : 'lazy'}
                            className="h-full w-full object-cover"
                            draggable={false}
                        />
                    </li>
                ))}
            </ul>

            {/*
             * The scrim, over the band rather than per slide so it stays put
             * while the pictures travel underneath it. A flat wash plus a
             * *vertical* gradient, never a side-weighted one: a horizontal
             * falloff is a physical direction, so under `dir="rtl"` the dark
             * end would stay left while the copy stays centred.
             */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-10 bg-souk-title/20"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-10 bg-gradient-to-t from-souk-title/70 via-transparent to-souk-title/45"
            />

            {/*
             * The claim, centred on the band. `pointer-events-none` on the
             * layer is load-bearing: it sits over the whole scroller, and
             * without it a swipe that started on the headline would do
             * nothing. Anything clickable inside takes them back — see the
             * call site.
             */}
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
