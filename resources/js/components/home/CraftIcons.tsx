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

/* ── Category glyphs ──────────────────────────────────────────────────
   One per top-level category, for the browse strip under the products
   hero. Same 64×64 grid and stroke weight as the three above so the whole
   row reads as one hand. `PotteryIcon` doubles as the pottery category. */

/** Satchel with a carry handle — leather goods. */
export function LeatherIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* handle */}
            <path d="M24 24v-3a8 8 0 0 1 16 0v3" />
            {/* body */}
            <path d="M11 24h42v23a5 5 0 0 1-5 5H16a5 5 0 0 1-5-5V24Z" />
            {/* flap edge */}
            <path d="M11 34h42" />
            {/* clasp */}
            <path d="M29 32h6v6h-6z" />
        </svg>
    );
}

/** Woven rug with knotted fringe — textiles and weaving. */
export function TextileIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* field */}
            <path d="M13 14h38v30H13z" />
            {/* inner border */}
            <path d="M19 20h26v18H19z" />
            {/* centre lozenge, the usual Berber motif */}
            <path d="M32 24l6 5-6 5-6-5 6-5Z" />
            {/* fringe */}
            <path d="M17 44v6M23 44v6M29 44v6M35 44v6M41 44v6M47 44v6" />
        </svg>
    );
}

/** Faceted stone — jewellery and accessories. */
export function JewelryIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* crown and pavilion */}
            <path d="M21 18h22l8 12-19 22-19-22 8-12Z" />
            {/* girdle */}
            <path d="M13 30h38" />
            {/* crown facets */}
            <path d="M27 18l5 12 5-12" />
            {/* pavilion ridge */}
            <path d="M32 30v22" />
        </svg>
    );
}

/** Mashrabiya lattice panel — wood and carpentry. */
export function WoodIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* frame */}
            <path d="M12 12h40v40H12z" />
            {/* cross members */}
            <path d="M32 12v40M12 32h40" />
            {/* diagonal lattice */}
            <path d="M22 22l20 20M42 22L22 42" />
        </svg>
    );
}

/** Lidded preserve jar — spices and food. */
export function SpiceIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* lid */}
            <path d="M23 12h18v7H23z" />
            {/* body */}
            <path d="M24 19c-5 3-8 8-8 13v13a6 6 0 0 0 6 6h20a6 6 0 0 0 6-6V32c0-5-3-10-8-13" />
            {/* label */}
            <path d="M24 33h16v11H24z" />
        </svg>
    );
}

/** Painter's palette — painting and art. */
export function ArtIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            <path d="M32 12c11 0 20 8 20 18 0 5-4 8-8 8h-4a4 4 0 0 0 0 8c0 3-3 6-8 6-11 0-20-9-20-20s9-20 20-20Z" />
            {/* wells */}
            <circle cx="24" cy="25" r="2.4" />
            <circle cx="34" cy="21" r="2.4" />
            <circle cx="43" cy="28" r="2.4" />
            <circle cx="22" cy="36" r="2.4" />
        </svg>
    );
}

/** Oil bottle with an argan leaf — cosmetics. */
export function ArganIcon({ className }: IconProps) {
    return (
        <svg {...BASE} className={className}>
            {/* cap */}
            <path d="M28 11h8v6h-8z" />
            {/* shoulders and body */}
            <path d="M28 17c0 4-7 6-7 13v16a6 6 0 0 0 6 6h10a6 6 0 0 0 6-6V30c0-7-7-9-7-13" />
            {/* leaf */}
            <path d="M17 36c-6-1-10-5-10-11 7-1 12 3 12 9" />
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
