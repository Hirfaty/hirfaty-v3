<?php

/**
 * Guards the ar / fr / en translation files against drift.
 *
 * These caught real bugs: `welcome.hero_cta` existed only in French, so the
 * Arabic and English home pages rendered the literal string "welcome.hero_cta",
 * and buyer.php / admin.php were listed in HandleInertiaRequests but had never
 * been created, so every t('buyer.*') call silently fell back to its raw key.
 */
$locales = ['fr', 'ar', 'en'];

// Must stay in step with HandleInertiaRequests::getTranslations().
$files = ['common', 'seller', 'buyer', 'admin', 'header', 'welcome'];

test('every translation file exists for every locale', function () use ($locales, $files) {
    foreach ($files as $file) {
        foreach ($locales as $locale) {
            expect(lang_path("{$locale}/{$file}.php"))
                ->toBeFile("Missing translation file: lang/{$locale}/{$file}.php");
        }
    }
});

test('every locale defines exactly the same keys', function () use ($locales, $files) {
    foreach ($files as $file) {
        $keysByLocale = [];

        foreach ($locales as $locale) {
            $keysByLocale[$locale] = array_keys(require lang_path("{$locale}/{$file}.php"));
        }

        $allKeys = array_unique(array_merge(...array_values($keysByLocale)));

        foreach ($locales as $locale) {
            $missing = array_diff($allKeys, $keysByLocale[$locale]);

            expect($missing)->toBeEmpty(
                "lang/{$locale}/{$file}.php is missing: ".implode(', ', $missing)
            );
        }
    }
});

test('no translation value is left empty', function () use ($locales, $files) {
    foreach ($files as $file) {
        foreach ($locales as $locale) {
            foreach (require lang_path("{$locale}/{$file}.php") as $key => $value) {
                expect(trim((string) $value))->not->toBe(
                    '', "Empty translation for {$locale}.{$file}.{$key}"
                );
            }
        }
    }
});

test('the files loaded by HandleInertiaRequests match the ones under test', function () use ($files) {
    $source = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

    preg_match('/\$files\s*=\s*\[(.*?)\]/s', $source, $matches);
    expect($matches)->not->toBeEmpty('Could not locate the $files list in HandleInertiaRequests.');

    preg_match_all("/'([a-z_]+)'/", $matches[1], $found);

    expect($found[1])->toEqualCanonicalizing(
        $files, 'HandleInertiaRequests loads a different set of translation files than this test covers.'
    );
});

test('the three supported locales are the ones the switcher accepts', function () use ($locales) {
    $source = file_get_contents(base_path('routes/web.php'));

    preg_match("/in_array\(\\\$locale,\s*\[(.*?)\]/s", $source, $matches);
    expect($matches)->not->toBeEmpty('Could not locate the locale whitelist in routes/web.php.');

    preg_match_all("/'([a-z]{2})'/", $matches[1], $found);

    expect($found[1])->toEqualCanonicalizing($locales);
});
