import { useId } from 'react';

/**
 * A tiling zellij star lattice, drawn as outlines rather than fills.
 *
 * Used as a decorative overlay on dark panels. The star is stroked, not
 * filled, so it reads as incised plasterwork rather than as a sticker — and
 * so a single `color` controls the whole thing.
 *
 * Deliberately *only* the stars: the pattern this came from also tiled a
 * square outline behind them, which rendered as graph paper across the panel.
 */
export default function ZellijStarsPattern({
    className,
    color = 'currentColor',
    size = 60,
}: {
    className?: string;
    /** Stroke colour. Defaults to `currentColor` so a Tailwind text
     *  utility on the same element can drive it. */
    color?: string;
    /** Tile size in px. The star is authored on a 60px grid and scaled. */
    size?: number;
}) {
    /*
     * `<pattern>` is referenced by id, and ids are document-global — two of
     * these on one page would otherwise collide and the second would paint
     * with the first's colour and scale. `useId()` keeps each instance's
     * pattern its own; the colons it returns are stripped because a raw
     * `url(#:r1:)` is awkward to escape if this ever moves into CSS.
     */
    const id = `zellij-stars-${useId().replace(/:/g, '')}`;

    return (
        <svg
            aria-hidden="true"
            className={className}
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <pattern
                    id={id}
                    x="0"
                    y="0"
                    width={size}
                    height={size}
                    patternUnits="userSpaceOnUse"
                >
                    <polygon
                        points="30,2 35,22 55,22 40,34 46,54 30,42 14,54 20,34 5,22 25,22"
                        fill="none"
                        stroke={color}
                        strokeWidth="1.2"
                        transform={`scale(${size / 60})`}
                    />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill={`url(#${id})`} />
        </svg>
    );
}
