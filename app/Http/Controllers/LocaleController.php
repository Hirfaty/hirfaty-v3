<?php

namespace App\Http\Controllers;

use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Getting a visitor onto the right language's URL.
 */
class LocaleController extends Controller
{
    public function __construct(private LocaleService $locales) {}

    /**
     * `/` and the pre-language URLs (/products/x, /about, …): send the
     * visitor to the same page in their language.
     *
     * 302, not 301: the target depends on who is asking (session, browser
     * language), and a browser caches a 301 forever — a visitor who later
     * switched language would keep being bounced to the old one. `Vary` tells
     * caches in between the same thing.
     */
    public function redirect(Request $request, string $path = ''): RedirectResponse
    {
        $target = $this->locales->prefix($path, $this->locales->preferred($request));
        $query = $request->getQueryString();

        return redirect($target.($query ? "?{$query}" : ''), 302)
            ->header('Vary', 'Accept-Language, Cookie');
    }

    /**
     * The language switcher. Remembers the choice and sends the visitor to
     * the page they were on, in the new language: /fr/products/x becomes
     * /ar/products/x; a private page (cart, account) reloads as-is and picks
     * the language up from the session.
     */
    public function switch(Request $request, string $language): RedirectResponse
    {
        abort_unless($this->locales->isSupported($language), 404);

        $this->locales->choose($request, $language);

        $previous = url()->previous();

        // Only ever bounce within this site — never to a Referer on another host.
        if (parse_url($previous, PHP_URL_HOST) !== $request->getHost()) {
            return redirect()->route('home', ['locale' => $language]);
        }

        return redirect($this->locales->swap($previous, $language));
    }
}
