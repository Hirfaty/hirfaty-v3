<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /*
     * Symfony's test requests announce `Accept-Language: en-us` unless told
     * otherwise, and a visitor with no saved language now gets their browser's
     * (LocaleService::preferred) — so every test would silently run in English.
     * French is the site's default and what the suite's expectations are
     * written in; a test about another language sets its own header or URL.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'fr');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
