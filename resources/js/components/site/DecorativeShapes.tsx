/**
 * Reusable organic SVG decorations. Purely presentational, never interactive.
 */

type ShapeProps = {
    className?: string;
    opacity?: number;
};

export function TerracottaBlob({ className = '', opacity = 1 }: ShapeProps) {
    return (
        <svg
            viewBox="0 0 600 600"
            className={className}
            style={{ opacity }}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M298 4c118 22 226 79 272 182 46 103 30 233-42 316-72 84-200 120-311 96C106 574 12 490 2 379-9 268 63 130 152 63 214 17 244-6 298 4Z"
            />
        </svg>
    );
}

export function PebbleBlob({ className = '', opacity = 1 }: ShapeProps) {
    return (
        <svg
            viewBox="0 0 520 480"
            className={className}
            style={{ opacity }}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M148 22c96-38 236-25 305 47 69 72 67 197 26 283-41 86-121 133-217 127-96-6-208-64-249-149C-28 245 22 121 92 66c22-17 34-33 56-44Z"
            />
        </svg>
    );
}

export function HalfDisc({ className = '', opacity = 1 }: ShapeProps) {
    return (
        <svg
            viewBox="0 0 300 600"
            className={className}
            style={{ opacity }}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M300 0v600H0C0 434 0 166 0 0h300Z"
                opacity="0"
            />
            <path
                fill="currentColor"
                d="M300 0C134 0 0 134 0 300s134 300 300 300V0Z"
            />
        </svg>
    );
}

export function ArcRibbon({ className = '', opacity = 1 }: ShapeProps) {
    return (
        <svg
            viewBox="0 0 400 400"
            className={className}
            style={{ opacity }}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="none"
                stroke="currentColor"
                strokeWidth="26"
                strokeLinecap="round"
                d="M20 380C20 180 180 20 380 20"
            />
        </svg>
    );
}

export function TearDrop({ className = '', opacity = 1 }: ShapeProps) {
    return (
        <svg
            viewBox="0 0 400 400"
            className={className}
            style={{ opacity }}
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M200 8c112 0 192 82 192 192 0 106-78 192-192 192S8 306 8 200C8 96 60 8 200 8Z"
            />
        </svg>
    );
}
