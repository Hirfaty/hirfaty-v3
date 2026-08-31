/**
 * Line-art craft glyphs for the "why Hirfaty" cards.
 *
 * Drawn as strokes on a shared 64×64 grid with matching weight and round
 * joins, so the three read as one family. `VaseIcon` — the filled Figma
 * export — is deliberately not mixed in here: at card size its solid mass
 * sits much heavier than a stroke glyph, which is what made the original
 * row look like the same icon repeated three times.
 *
 * Colour comes from `currentColor` so a card can tint them.
 */

type IconProps = { className?: string };

const BASE = {
    viewBox: '0 0 64 64',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    xmlns: 'http://www.w3.org/2000/svg',
    'aria-hidden': true,
    focusable: 'false' as const,
};

/** Thrown pot on the wheel — the making itself. */
export function PotteryIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* rim */}
            <path d="M24 11h16" />
            {/* shoulders swelling out of the neck, down to the foot */}
            <path d="M26 11v3c0 5-10 9-10 19 0 10 7 16 16 16s16-6 16-16c0-10-10-14-10-19v-3" />
            {/* decorative band across the belly */}
            <path d="M18.5 36h27" />
            {/* the wheel head it is standing on */}
            <path d="M14 53h36" />
            <path d="M22 49v4M42 49v4" />
        </svg>
    );
}

/** Horseshoe arch — the workshop doorway you are buying through. */
export function AtelierArchIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* outer arch springing from the jambs */}
            <path d="M13 54V29a19 19 0 0 1 38 0v25" />
            {/* inner arch, the reveal */}
            <path d="M22 54V30a10 10 0 0 1 20 0v24" />
            {/* threshold */}
            <path d="M9 54h46" />
            {/* keystone lamp */}
            <path d="M32 20v4" />
        </svg>
    );
}

/**
 * Eight-point khatim star — the zellige signature, standing for the piece
 * being one of a kind.
 *
 * The polygon is a 16-vertex star: alternating outer (r 21) and inner
 * (r 8.6) radii every 22.5° about the centre, rounded to 2dp.
 */
export function ZellijStarIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            <polygon
                points="
                    53,32 39.94,35.29 46.85,46.85 35.29,39.94 32,53
                    28.71,39.94 17.15,46.85 24.06,35.29 11,32
                    24.06,28.71 17.15,17.15 28.71,24.06 32,11
                    35.29,24.06 46.85,17.15 39.94,28.71
                "
            />
            <circle cx="32" cy="32" r="6.5" />
        </svg>
    );
}
