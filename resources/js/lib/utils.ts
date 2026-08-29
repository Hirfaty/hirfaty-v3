import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(
    date: string | Date | null | undefined,
    locale: string = 'fr-MA',
) {
    if (!date) {
        return 'N/A';
    }

    const d = typeof date === 'string' ? new Date(date) : date;

    // Check if valid date
    if (isNaN(d.getTime())) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(d);
}
