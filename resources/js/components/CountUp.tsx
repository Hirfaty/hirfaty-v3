import { useEffect, useRef, useState } from 'react';

interface CountUpProps {
    value: string;
    duration?: number;
    className?: string;
}

/**
 * Counts a numeric string up from 0 once it scrolls into view (e.g. "41" or
 * "100%"). Non-numeric values (e.g. "Bientôt") just render as-is.
 */
export function CountUp({ value, duration = 1200, className }: CountUpProps) {
    const match = value.match(/^(\d+)(.*)$/);
    const ref = useRef<HTMLSpanElement>(null);
    const [display, setDisplay] = useState(match ? '0' + match[2] : value);

    useEffect(() => {
        if (!match) return;
        const el = ref.current;
        if (!el) return;

        const target = parseInt(match[1], 10);
        const suffix = match[2];

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return;
                observer.disconnect();

                const start = performance.now();
                const step = (now: number) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    setDisplay(Math.round(eased * target) + suffix);
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            },
            { threshold: 0.5 },
        );

        observer.observe(el);
        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, duration]);

    return (
        <span ref={ref} className={className}>
            {display}
        </span>
    );
}

export default CountUp;
