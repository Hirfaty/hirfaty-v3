import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import craftLogin from '@/images/auth/login.jpg';
import craftPotter from '@/images/auth/register-1.jpg';
import craftSilver from '@/images/auth/register-2.jpg';

const CARDS = [craftPotter, craftSilver, craftLogin] as const;

/** How long each photograph stays at the front. */
const DWELL_MS = 3800;

/**
 * Where a card sits depending on how far back in the deck it is. Index 0 is
 * the front card; the rest fan up and to the side like a hand of cards.
 *
 * `x` is in rem and gets mirrored in RTL along with the rotation, so the fan
 * opens away from the form in both directions rather than into it.
 */
const SLOTS = [
    { x: 0, y: 0, rotate: -4, scale: 1, opacity: 1, z: 'z-30' },
    { x: 2.5, y: -1.25, rotate: 5, scale: 0.95, opacity: 0.92, z: 'z-20' },
    { x: 5, y: -2.5, rotate: 12, scale: 0.9, opacity: 0.78, z: 'z-10' },
] as const;

/**
 * The fanned deck of craft photographs beside step 1 of signup.
 *
 * Auto-advances: the front card cycles to the back every few seconds, so the
 * panel keeps moving without the visitor doing anything. Three deliberate
 * concessions, because this is decoration sitting next to a form and must
 * never get in the way of it:
 *
 *  - it pauses while hovered or focused, so someone reading it can;
 *  - it does not auto-advance at all under `prefers-reduced-motion`, though
 *    clicking still works;
 *  - the whole thing is `aria-hidden` and the cards carry empty alt text —
 *    there is no information here that isn't already in the heading.
 */
export default function CraftCardStack() {
    const { isRTL } = useTranslation();

    const [front, setFront] = useState(0);
    const [paused, setPaused] = useState(false);

    useEffect(() => {
        if (paused) {
            return;
        }

        /*
         * Read rather than stored in state: this only decides whether to set a
         * timer, so keeping it out of the render path avoids a second render
         * on mount just to learn something CSS already knows.
         */
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const timer = setInterval(
            () => setFront((f) => (f + 1) % CARDS.length),
            DWELL_MS,
        );

        return () => clearInterval(timer);
    }, [paused]);

    return (
        <div
            aria-hidden="true"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            className="relative mx-auto aspect-[4/5] w-full max-w-[19rem]"
        >
            {CARDS.map((src, i) => {
                // 0 = front, then progressively further back in the deck.
                const slot = SLOTS[(i - front + CARDS.length) % CARDS.length];
                const direction = isRTL ? -1 : 1;

                return (
                    <button
                        key={i}
                        type="button"
                        tabIndex={-1}
                        onClick={() => setFront(i)}
                        style={{
                            transform: `translate(${slot.x * direction}rem, ${slot.y}rem) rotate(${slot.rotate * direction}deg) scale(${slot.scale})`,
                            opacity: slot.opacity,
                        }}
                        className={`absolute inset-0 overflow-hidden rounded-2xl shadow-xl ring-1 shadow-souk-brown/15 ring-souk-brown/15 transition-all duration-700 ease-out motion-reduce:transition-none ${slot.z}`}
                    >
                        <img
                            src={src}
                            alt=""
                            loading={i === 0 ? 'eager' : 'lazy'}
                            className="h-full w-full object-cover"
                        />
                        {/* Warms the photographs into the paper background so
                            the deck reads as part of the page rather than
                            three pasted rectangles. */}
                        <span className="absolute inset-0 bg-gradient-to-t from-souk-title/35 via-transparent to-transparent" />
                    </button>
                );
            })}
        </div>
    );
}
