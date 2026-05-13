import type { HTMLAttributes } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { updateAppearance } = useAppearance();

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1',
                className,
            )}
            {...props}
        >
            <button
                onClick={updateAppearance}
                className="flex items-center rounded-md bg-white px-3.5 py-1.5 text-sm shadow-xs"
            >
                Light mode only
            </button>
        </div>
    );
}
