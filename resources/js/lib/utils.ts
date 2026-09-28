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

/**
 * "il y a 5 minutes", "hier", … — for feeds where the exact date matters less
 * than how fresh something is. Falls back to formatDate past a week.
 */
export function formatRelativeTime(
    date: string | null | undefined,
    locale: string = 'fr-MA',
) {
    if (!date) {
        return '';
    }

    const d = new Date(date);

    if (isNaN(d.getTime())) {
        return '';
    }

    const seconds = Math.round((d.getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    if (Math.abs(seconds) < 60) {
        return rtf.format(0, 'second');
    }

    const minutes = Math.round(seconds / 60);

    if (Math.abs(minutes) < 60) {
        return rtf.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (Math.abs(hours) < 24) {
        return rtf.format(hours, 'hour');
    }

    const days = Math.round(hours / 24);

    if (Math.abs(days) < 7) {
        return rtf.format(days, 'day');
    }

    return formatDate(d, locale);
}
