import type { CSSProperties } from 'react';

/**
 * Warm abstract fill used in place of artisan photography.
 * Variants generate distinct, hand-feeling textures from the brand palette
 * using layered CSS gradients — no stock imagery, no blue gradients.
 */
type Variant =
    | 'pottery'
    | 'textile'
    | 'leather'
    | 'jewelry'
    | 'spice'
    | 'wood'
    | 'ink'
    | 'clay'
    | 'sand'
    | 'olive'
    | 'cream';

const styles: Record<Variant, CSSProperties> = {
    pottery: {
        background:
            'radial-gradient(120% 80% at 30% 30%, hsl(18 70% 60%) 0%, hsl(14 65% 42%) 55%, hsl(14 55% 28%) 100%)',
    },
    textile: {
        backgroundColor: 'hsl(22 45% 78%)',
        backgroundImage:
            'repeating-linear-gradient(90deg, hsl(14 55% 35% / 0.18) 0 1px, transparent 1px 6px), repeating-linear-gradient(0deg, hsl(14 55% 35% / 0.10) 0 1px, transparent 1px 12px)',
    },
    leather: {
        background:
            'radial-gradient(130% 90% at 70% 40%, hsl(22 55% 40%) 0%, hsl(20 45% 28%) 60%, hsl(18 35% 18%) 100%)',
    },
    jewelry: {
        background:
            'radial-gradient(80% 60% at 40% 35%, hsl(36 40% 88%) 0%, hsl(32 30% 75%) 50%, hsl(232 30% 22%) 100%)',
    },
    spice: {
        backgroundColor: 'hsl(28 75% 50%)',
        backgroundImage:
            'radial-gradient(40% 60% at 25% 30%, hsl(8 70% 38%) 0%, transparent 60%), radial-gradient(50% 70% at 80% 70%, hsl(45 80% 55%) 0%, transparent 60%)',
    },
    wood: {
        backgroundColor: 'hsl(28 35% 38%)',
        backgroundImage:
            'repeating-linear-gradient(8deg, hsl(20 40% 22% / 0.55) 0 2px, transparent 2px 9px), repeating-linear-gradient(8deg, hsl(36 30% 60% / 0.18) 0 1px, transparent 1px 22px)',
    },
    ink: { background: 'hsl(232 36% 20%)' },
    clay: { background: 'hsl(22 45% 78%)' },
    sand: { background: 'hsl(34 40% 84%)' },
    olive: { background: 'hsl(70 18% 38%)' },
    cream: { background: 'hsl(36 38% 95%)' },
};

interface Props {
    variant: Variant;
    className?: string;
    children?: React.ReactNode;
    style?: CSSProperties;
}

export const WarmFill = ({
    variant,
    className = '',
    children,
    style,
}: Props) => (
    <div className={className} style={{ ...styles[variant], ...style }}>
        {children}
    </div>
);

export default WarmFill;
