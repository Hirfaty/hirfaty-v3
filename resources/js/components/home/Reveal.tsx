import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';

type RevealProps = {
    children: ReactNode;
    /** Stagger in ms, for sequencing siblings. */
    delay?: number;
    /** Direction the element travels in from. */
    from?: 'up' | 'left' | 'right' | 'scale';
    className?: string;
};

const OFFSCREEN: Record<NonNullable<RevealProps['from']>, string> = {
    up: 'translate-y-10',
    left: '-translate-x-10',
    right: 'translate-x-10',
    scale: 'scale-[0.94]',
};

/**
 * Reveals its children once they scroll into view, then stops observing.
 *
 * Honours `prefers-reduced-motion` by rendering straight to the visible state,
 * so the content is never hidden from anyone who has motion turned off (and
 * never hidden if IntersectionObserver is unavailable).
 */
export default function Reveal({
    children,
    delay = 0,
    from = 'up',
    className = '',
}: RevealProps) {
    const ref = useRef<HTMLDivElement | null>(null);

    /*
     * Resolved during the initial render rather than in the effect below: if
     * motion is off (or there is nothing to observe with) the content must
     * simply start visible, and flipping that from inside an effect would mean
     * an extra render pass — plus a frame where the content is invisible.
     */
    const [shown, setShown] = useState(
        () =>
            typeof window !== 'undefined' &&
            (typeof IntersectionObserver === 'undefined' ||
                window.matchMedia('(prefers-reduced-motion: reduce)').matches),
    );

    useEffect(() => {
        const element = ref.current;

        if (shown || !element) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];

                if (!entry) {
                    return;
                }

                /*
                 * `isIntersecting` alone is not enough: a fast wheel flick, an
                 * anchor jump or the browser restoring a saved scroll position
                 * can carry the element from below the fold to above it without
                 * ever producing an intersecting frame, which would strand it
                 * at opacity 0 forever. Treating "already scrolled past" as
                 * revealed keeps the content readable in those cases.
                 */
                if (entry.isIntersecting || entry.boundingClientRect.top < 0) {
                    setShown(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -60px 0px' },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [shown]);

    return (
        <div
            ref={ref}
            style={{ transitionDelay: `${delay}ms` }}
            className={`transition-all duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] ${
                shown
                    ? 'translate-x-0 translate-y-0 scale-100 opacity-100'
                    : `opacity-0 ${OFFSCREEN[from]}`
            } ${className}`}
        >
            {children}
        </div>
    );
}
