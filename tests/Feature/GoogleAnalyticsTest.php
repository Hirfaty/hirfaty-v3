<?php

test('the analytics tag is printed when a measurement id is set', function () {
    config(['services.google_analytics.measurement_id' => 'G-TEST123']);

    $this->get('http://hirfaty.com/fr/products')
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)
        ->assertSee("gtag('config', \"G-TEST123\")", false);
});

test('no analytics tag without a measurement id', function () {
    config(['services.google_analytics.measurement_id' => null]);

    $this->get('http://hirfaty.com/fr/products')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false);
});

test('the staging host never loads analytics', function () {
    config(['services.google_analytics.measurement_id' => 'G-TEST123']);

    $this->get('http://'.config('hirfaty.staging_host').'/fr/products')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false);
});
