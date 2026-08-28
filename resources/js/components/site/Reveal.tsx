import { useEffect, useRef, useState, type ReactNode } from 'react';

interface RevealProps {
    children: ReactNode;
    delay?: number;
    className?: string;
    as?: 'div' | 'section' | 'li' | 'article';
}

export function Reveal({
    children,
    delay = 0,
    className = '',
    as = 'div',
}: RevealProps) {
    const ref = useRef<HTMLElement | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const node = ref.current;
        if (!node) return;
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setVisible(true);
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -60px 0px' },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    const Tag = as as 'div';

    return (
        <Tag
            ref={ref as React.RefObject<HTMLDivElement>}
            className={`${visible ? 'reveal-in' : 'reveal'} ${className}`}
            style={{ animationDelay: `${delay}ms` }}
        >
            {children}
        </Tag>
    );
}
