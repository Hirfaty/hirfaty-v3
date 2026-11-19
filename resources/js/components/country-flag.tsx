import { useEffect, useState } from 'react';

/*
 * One small SVG per country, each its own file in the build and fetched only
 * when shown — the phone field shows one flag at a time, and bundling all 267
 * would put close to a megabyte in front of the sign-in page.
 *
 * SVGs rather than emoji flags: Windows draws a flag emoji as two letters
 * ("MA"), which is exactly the platform a lot of shoppers are on.
 */
const flags = import.meta.glob<string>(
    '/node_modules/country-flag-icons/3x2/*.svg',
    { query: '?url', import: 'default' },
);

const loaderFor = (country: string) =>
    flags[`/node_modules/country-flag-icons/3x2/${country.toUpperCase()}.svg`];

/**
 * The flag of an ISO 3166-1 country (`MA`, `FR`…). Renders a neutral
 * placeholder of the same size while it loads, or when the code has no flag,
 * so the field around it never jumps.
 */
export default function CountryFlag({
    country,
    title,
    className = 'h-4 w-6',
}: {
    country: string | null | undefined;
    title?: string;
    className?: string;
}) {
    const [loaded, setLoaded] = useState<{ country: string; url: string }>();

    useEffect(() => {
        const load = country ? loaderFor(country) : undefined;

        if (!country || !load) {
            return;
        }

        let cancelled = false;

        load().then((url) => {
            if (!cancelled) {
                setLoaded({ country, url });
            }
        });

        return () => {
            cancelled = true;
        };
    }, [country]);

    // Only the flag for the country asked for — a stale one from the
    // previous number would say the wrong thing while the new one loads.
    const url = loaded && loaded.country === country ? loaded.url : null;

    return url ? (
        <img
            src={url}
            alt={title ?? ''}
            title={title}
            className={`${className} shrink-0 rounded-[2px] object-cover shadow-[0_0_0_1px_rgba(0,0,0,0.08)]`}
        />
    ) : (
        <span
            aria-hidden
            className={`${className} inline-block shrink-0 rounded-[2px] bg-souk-brown/10`}
        />
    );
}
