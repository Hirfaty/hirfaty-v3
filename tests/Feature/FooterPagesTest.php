<?php

/**
 * The footer's static pages.
 *
 * Two things matter here and neither is about content. First, every page the
 * footer offers actually resolves — the footer's own comment says a dead link
 * is worse than no link, and this is what holds that line. Second, the three
 * locales carry the same keys: `useTranslation()` falls back to rendering the
 * raw key string on the page, so a missing Arabic entry shows up as
 * "pages.rewards.heading" to the reader rather than as an error anywhere.
 */
test('every page the footer links to resolves', function (string $uri, string $component) {
    $this->get($uri)
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component($component)->etc());
})->with([
    ['/affiliate', 'affiliate'],
    ['/rewards', 'rewards'],
    ['/privacy', 'privacy'],
    ['/shipping-policy', 'shipping-policy'],
    // Pre-existing, listed so a footer restructure cannot quietly orphan one.
    ['/about', 'about'],
    ['/contact', 'contact'],
    ['/how-it-works', 'how-it-works'],
    ['/professional-account', 'buyer/professional-account'],
]);

test('the blog link the footer points at resolves', function () {
    // Not a static page — /blog is a real controller — but it is in the same
    // column, so it belongs in the same guarantee.
    $this->get('/blog')->assertSuccessful();
});

test('the new page copy exists in all three locales', function (string $key) {
    $missing = [];

    foreach (['en', 'fr', 'ar'] as $locale) {
        app()->setLocale($locale);

        // Laravel returns the key itself when there is no translation, which
        // is exactly what the reader would see on the page.
        if (__($key) === $key) {
            $missing[] = $locale;
        }
    }

    expect($missing)->toBe([], "Missing {$key} in: ".implode(', ', $missing));
})->with([
    'pages.affiliate.heading',
    'pages.affiliate.cta.button',
    'pages.rewards.heading',
    'pages.rewards.tiers.platinum_body',
    'pages.rewards.soon.body',
    'pages.privacy.heading',
    'pages.privacy.contact.body',
    'pages.shipping.heading',
    'pages.shipping.morocco.body',
    'common.footer.explore.title',
    'common.footer.about.title',
    'common.footer.care.title',
    'common.footer.care.bulk',
]);
