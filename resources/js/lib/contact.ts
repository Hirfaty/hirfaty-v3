import { AsYouType, parsePhoneNumberFromString } from 'libphonenumber-js';
import type { CountryCode } from 'libphonenumber-js';

/**
 * Reading the one "email or phone" field that sign-in and checkout share —
 * the browser half of App\Support\Contact. Its job is to catch a wrong
 * address or number *before* a code is sent to it; the server re-checks
 * everything and has the last word.
 */

/** A number typed without its country code is read as Moroccan. */
export const DEFAULT_COUNTRY: CountryCode = 'MA';

export type ContactKind = 'empty' | 'email' | 'phone';

/** An `@` or any letter means an address; otherwise it is a number. */
export function contactKind(raw: string): ContactKind {
    const value = raw.trim();

    if (value === '') {
        return 'empty';
    }

    return value.includes('@') || /\p{L}/u.test(value) ? 'email' : 'phone';
}

export type PhoneReading = {
    /** The country the number belongs to, once there are enough digits. */
    country: CountryCode | undefined;
    /** `+212 6 12 34 56 78` — only when the number is valid. */
    international: string | null;
    /** A number that can exist: right length, real prefix for the country. */
    valid: boolean;
    /**
     * Too few digits to judge yet. The field does not call a number wrong
     * while it is still being typed.
     */
    incomplete: boolean;
};

export function readPhone(raw: string): PhoneReading {
    const value = raw.trim();

    // As the digits arrive: `+33 6…` is French from the third character,
    // `06…` is Moroccan from the first.
    const typing = new AsYouType(DEFAULT_COUNTRY);
    typing.input(value);

    // Parsed whole: also understands `00212…`, the way many people abroad
    // write the international prefix.
    const parsed = parsePhoneNumberFromString(value, DEFAULT_COUNTRY);
    const valid = parsed?.isValid() ?? false;

    const country =
        (valid ? parsed?.country : undefined) ??
        typing.getCountry() ??
        // Written the national way (no + or 00): Moroccan, as the server
        // reads it.
        (/^(\+|00)/.test(value) ? undefined : DEFAULT_COUNTRY);

    const digits = value.replace(/\D/g, '').length;

    return {
        country,
        international: valid ? parsed!.formatInternational() : null,
        valid,
        incomplete: !valid && digits < 9,
    };
}

/*
 * The inboxes most shoppers here use. A typo in one of them is the commonest
 * way an order confirmation goes nowhere — "gmial.com", "hotmail.con".
 */
const COMMON_DOMAINS = [
    'gmail.com',
    'hotmail.com',
    'hotmail.fr',
    'outlook.com',
    'outlook.fr',
    'live.com',
    'live.fr',
    'yahoo.com',
    'yahoo.fr',
    'icloud.com',
    'msn.com',
    'aol.com',
    'menara.ma',
    'orange.fr',
    'free.fr',
    'sfr.fr',
    'wanadoo.fr',
    'laposte.net',
    'gmx.fr',
    'gmx.com',
    'protonmail.com',
    'proton.me',
];

function distance(a: string, b: string): number {
    const row = Array.from({ length: b.length + 1 }, (_, i) => i);

    for (let i = 1; i <= a.length; i++) {
        let previous = row[0];
        row[0] = i;

        for (let j = 1; j <= b.length; j++) {
            const current = row[j];
            row[j] = Math.min(
                row[j] + 1,
                row[j - 1] + 1,
                previous + (a[i - 1] === b[j - 1] ? 0 : 1),
            );
            previous = current;
        }
    }

    return row[b.length];
}

/**
 * `ahmed@gmial.com` → `ahmed@gmail.com`; null when the domain is one of the
 * common ones already, or nothing common is close to it. A suggestion only —
 * plenty of real domains are one letter away from gmail.com.
 */
export function suggestEmail(raw: string): string | null {
    const value = raw.trim().toLowerCase();
    const at = value.lastIndexOf('@');

    if (at < 1) {
        return null;
    }

    const local = value.slice(0, at);
    const domain = value.slice(at + 1);

    if (domain.length < 4 || COMMON_DOMAINS.includes(domain)) {
        return null;
    }

    let best: { domain: string; score: number } | null = null;

    for (const candidate of COMMON_DOMAINS) {
        const score = distance(domain, candidate);

        if (score <= 2 && (!best || score < best.score)) {
            best = { domain: candidate, score };
        }
    }

    return best ? `${local}@${best.domain}` : null;
}

/** Roughly an address: something@something.tld. The server decides. */
export function looksLikeEmail(raw: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(raw.trim());
}

/** "Maroc" / "المغرب" / "Morocco" — the browser knows every country's name. */
export function countryName(country: string, locale: string): string {
    try {
        return (
            new Intl.DisplayNames([locale], { type: 'region' }).of(country) ??
            country
        );
    } catch {
        return country;
    }
}
