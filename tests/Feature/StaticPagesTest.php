<?php

use Inertia\Testing\AssertableInertia;

/**
 * The two pages the footer links to, and the translations behind them.
 *
 * The translation checks matter more than they look. `t()` fails silently: a
 * missing key renders the raw key string onto the page (see
 * `use-translation.tsx`), and `HandleInertiaRequests::getTranslations()` skips
 * a missing *file* without a word. So a locale that was forgotten does not
 * throw anywhere — it just quietly ships `pages.how.heading` as body copy.
 */
it('serves the how-it-works page to a visitor who is not signed in', function () {
    $this->get('/how-it-works')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('how-it-works'));
});

it('serves the contact page to a visitor who is not signed in', function () {
    $this->get('/contact')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('contact'));
});

/**
 * Read straight off the shared props rather than through `assertInertia`'s
 * fluent paths. Translation keys are *flat strings containing dots* — see the
 * `getTranslations()` note in CLAUDE.md — so `where('translations.pages.contact.heading')`
 * would look for a nested `contact` array that does not exist and fail on a
 * file that is perfectly fine.
 *
 * @return array<string, array<string, string>>
 */
function sharedTranslations(string $path = '/'): array
{
    return test()->get($path)->viewData('page')['props']['translations'];
}

/**
 * The file list in `getTranslations()` is hardcoded, so a new `pages.php` is
 * invisible to the frontend until it is added there. It was, but nothing else
 * would catch it being dropped.
 */
it('ships the pages translations to the frontend', function () {
    $translations = sharedTranslations('/contact');

    expect($translations)->toHaveKey('pages')
        ->and($translations['pages']['contact.heading'])->toBe('Parlons-en')
        ->and($translations['pages']['how.heading'])->toBe('De l’atelier à votre porte');
});

it('ships the new footer translations to the frontend', function () {
    $common = sharedTranslations()['common'];

    expect($common)->toHaveKeys([
        'footer.blurb',
        'footer.buyers.explore',
        'footer.company.contact',
    ]);
});

/**
 * All three locales must carry the same keys, or the page renders raw key
 * strings in whichever one was missed.
 */
it('defines every pages key in all three locales', function () {
    $reference = require lang_path('fr/pages.php');

    foreach (['en', 'ar'] as $locale) {
        $translated = require lang_path("{$locale}/pages.php");

        expect(array_keys($translated))
            ->toEqualCanonicalizing(
                array_keys($reference),
                "lang/{$locale}/pages.php does not match lang/fr/pages.php"
            );

        foreach ($translated as $key => $value) {
            expect($value)->not->toBeEmpty("{$locale}/pages.php: '{$key}' is empty");
        }
    }
});

it('defines every new footer key in all three locales', function () {
    $keys = [
        'footer.blurb',
        'footer.buyers.title',
        'footer.buyers.explore',
        'footer.buyers.how',
        'footer.artisans.title',
        'footer.artisans.sell',
        'footer.company.title',
        'footer.company.contact',
        'footer.follow.title',
        'footer.copyright',
        'footer.shipping',
    ];

    foreach (['fr', 'en', 'ar'] as $locale) {
        $common = require lang_path("{$locale}/common.php");

        foreach ($keys as $key) {
            expect($common[$key] ?? null)
                ->not->toBeEmpty("lang/{$locale}/common.php is missing '{$key}'");
        }
    }
});

/**
 * The copyright line is the one string with a placeholder, and `t()` does not
 * substitute — the footer does it by hand. If a locale spells the token
 * differently the year silently never appears.
 */
it('keeps the :year placeholder in every copyright translation', function () {
    foreach (['fr', 'en', 'ar'] as $locale) {
        $common = require lang_path("{$locale}/common.php");

        expect($common['footer.copyright'])->toContain(':year');
    }
});

/**
 * Every footer destination has to resolve. The point of the rewrite was to
 * stop the footer pointing twelve links at `#`.
 *
 * `/sell` rather than `/register`: the footer's artisan link moved there when
 * signup merged into /login and the two account-kind doors left the auth
 * namespace. This dataset has to track SiteFooter's NAV_COLUMNS.
 */
it('points every footer link at a page that exists', function (string $path) {
    $this->get($path)->assertSuccessful();
})->with(['/products', '/how-it-works', '/contact', '/sell']);
