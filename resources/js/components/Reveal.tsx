import { useEffect, useRef, useState } from 'react';

interface RevealProps {
    children: React.ReactNode;
    className?: string;
    delay?: number;
}

/**
 * Fades + slides children up into place the first time they scroll into
 * view. Pure CSS transition driven by an IntersectionObserver — no
 * animation library required.
 */
export function Reveal({ children, className = '', delay = 0 }: RevealProps) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -80px 0px' },
        );

        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={`transition-all duration-700 ease-out ${
                visible
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-8 opacity-0'
            } ${className}`}
            style={{ transitionDelay: `${delay}ms` }}
        >
            {children}
        </div>
    );
}

export default Reveal;
