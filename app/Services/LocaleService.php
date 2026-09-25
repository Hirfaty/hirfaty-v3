<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Which language a request is in, and how to move a URL between languages.
 *
 * Public pages carry their language in the URL — /fr/products, /ar/products,
 * /en/products — so each language is its own page a search engine can index.
 * Private pages (cart, checkout, account, admin, seller) are never indexed and
 * stay unprefixed; they follow the language stored in the session.
 *
 * The session keeps two things apart, because they mean different things:
 *
 *  - `locale_choice` — the visitor *picked* a language (the switcher, a
 *    seller's language setting). Only this outranks the browser.
 *  - `locale` — the language they were last reading, whether picked or not.
 *    Private pages follow it, so a buyer who arrives on /ar/… from a shared
 *    link and goes on to the cart stays in Arabic.
 *
 * Merely opening an /en/… link must not pin the whole site to English: that
 * is how a French browser used to keep landing on /en after one English page.
 */
class LocaleService
{
    /** Order matters: the first is the fallback when nothing else matches. */
    public const SUPPORTED = ['fr', 'ar', 'en'];

    public const DEFAULT = 'fr';

    /** Route-constraint pattern for the `{locale}` URL segment. */
    public const PATTERN = 'fr|ar|en';

    /** The language last read in — URL or choice. Private pages follow it. */
    public const SESSION_CURRENT = 'locale';

    /** A language the visitor explicitly picked. Outranks the browser. */
    public const SESSION_CHOICE = 'locale_choice';

    public function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    /**
     * Where to send someone arriving without a language — the bare domain or
     * an old unprefixed link: their explicit choice, else their browser's
     * language, else French.
     *
     * Symfony matches "ar-MA" to "ar" and "en-GB,en;q=0.9" to "en", and falls
     * back to the first supported locale — French — when the header is
     * missing (most crawlers) or names nothing we have.
     */
    public function preferred(Request $request): string
    {
        $chosen = $this->fromSession($request, self::SESSION_CHOICE);

        if ($chosen !== null) {
            return $chosen;
        }

        $fromBrowser = $request->getPreferredLanguage(self::SUPPORTED);

        return $this->isSupported($fromBrowser) ? $fromBrowser : self::DEFAULT;
    }

    /**
     * The language of a private page (no language in its URL): whatever the
     * visitor was last reading, else where they would have been sent.
     */
    public function current(Request $request): string
    {
        return $this->fromSession($request, self::SESSION_CURRENT) ?? $this->preferred($request);
    }

    /** The visitor opened a page in this language. */
    public function remember(Request $request, string $locale): void
    {
        $request->session()->put(self::SESSION_CURRENT, $locale);
    }

    /** The visitor picked this language — it now outranks their browser. */
    public function choose(Request $request, string $locale): void
    {
        $request->session()->put([
            self::SESSION_CURRENT => $locale,
            self::SESSION_CHOICE => $locale,
        ]);
    }

    private function fromSession(Request $request, string $key): ?string
    {
        $value = $request->hasSession() ? $request->session()->get($key) : null;

        return $this->isSupported($value) ? $value : null;
    }

    /**
     * The same URL in another language: "/fr/products/x?y=1" → "/ar/products/x?y=1".
     * A URL with no language segment (a private page) comes back unchanged,
     * since those pages already follow the session.
     */
    public function swap(string $url, string $locale): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';

        $segments = explode('/', ltrim($path, '/'));

        if (! $this->isSupported($segments[0] ?? null)) {
            return $url;
        }

        $segments[0] = $locale;

        return '/'.implode('/', $segments).(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * "/fr/products/x?y=1" → "/products/x?y=1": the language-neutral URL,
     * which redirects each visitor to their own language. That makes it the
     * right `x-default` for hreflang.
     */
    public function strip(string $url): string
    {
        $parts = parse_url($url);
        $segments = explode('/', ltrim($parts['path'] ?? '/', '/'));

        if ($this->isSupported($segments[0] ?? null)) {
            array_shift($segments);
        }

        return '/'.implode('/', $segments).(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** "/products/x" → "/fr/products/x" — for the unprefixed legacy URLs. */
    public function prefix(string $path, string $locale): string
    {
        $path = trim($path, '/');

        return '/'.$locale.($path === '' ? '' : '/'.$path);
    }
}
