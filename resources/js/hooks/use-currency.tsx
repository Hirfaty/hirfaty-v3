import { usePage } from '@inertiajs/react';

export type CurrencyPayload = {
    code: string;
    symbol: string;
    decimals: number;
    /** BCP-47 tag driving Intl.NumberFormat separators and symbol placement. */
    locale: string;
    /** How many units of `code` one MAD buys. */
    rate: number;
    /** True when the visitor is already reading the base currency (MAD). */
    is_base: boolean;
};

export type CurrencyOption = { code: string; symbol: string };

/** What the server falls back to, mirrored here for the first paint. */
const BASE: CurrencyPayload = {
    code: 'MAD',
    symbol: 'MAD',
    decimals: 0,
    locale: 'fr-MA',
    rate: 1,
    is_base: true,
};

/**
 * Prices, in whatever currency this visitor is reading.
 *
 * Everything the server sends is MAD — products are priced in it, orders
 * settle in it. This converts on the way to the screen using the rate shared
 * on every response (HandleInertiaRequests), so a listing can render dozens
 * of prices without a round trip each.
 *
 * `money()` takes a MAD amount and returns a formatted string. Pass
 * `{ convert: false }` for a figure that is genuinely MAD and must stay that
 * way regardless of what the buyer is browsing in — the amount actually
 * charged at checkout, for instance.
 */
export function useCurrency() {
    const page = usePage().props as unknown as {
        currency?: CurrencyPayload;
        currencyOptions?: CurrencyOption[];
    };

    // `currency` is a lazy prop, so a response that never evaluated it (or a
    // component rendered outside a normal page load) legitimately has none.
    const currency = page.currency ?? BASE;
    const options = page.currencyOptions ?? [];

    const format = (
        value: number,
        code: string,
        decimals: number,
        locale: string,
    ) => {
        const meta = code === currency.code ? currency : null;
        const symbol = meta?.symbol ?? code;

        const formatted = new Intl.NumberFormat(locale, {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimals,
        }).format(value);

        /*
         * Symbol-before for the Latin currencies that are written that way,
         * symbol-after for MAD and the Gulf codes. Done by hand rather than
         * with Intl's `style: 'currency'` because that renders MAD as "MAD"
         * in some locales and "د.م." in others, and the storefront should
         * read the same way everywhere.
         */
        return ['€', '$', '£', 'CA$'].includes(symbol)
            ? `${symbol}${formatted}`
            : `${formatted} ${symbol}`;
    };

    /** A MAD amount, rendered in the active currency. */
    const money = (
        value: number | string | null | undefined,
        { convert = true }: { convert?: boolean } = {},
    ): string => {
        const mad = Number(value ?? 0);

        if (!convert || currency.is_base) {
            return format(
                Math.round(mad),
                BASE.code,
                BASE.decimals,
                BASE.locale,
            );
        }

        return format(
            mad * currency.rate,
            currency.code,
            currency.decimals,
            currency.locale,
        );
    };

    /** The same amount in MAD, for "charged in…" notes beside a converted price. */
    const baseMoney = (value: number | string | null | undefined): string =>
        money(value, { convert: false });

    return { currency, options, money, baseMoney, isBase: currency.is_base };
}
