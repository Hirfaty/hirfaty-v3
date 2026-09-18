<?php

namespace Database\Seeders;

use App\Models\AmanaDeliveryRate;
use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Amana's rate card.
 *
 * ⚠ PLACEHOLDER FIGURES. Every destination is seeded at the flat 20 MAD the
 * platform charged before rates were per-destination, so switching the table
 * on changes nothing a buyer sees until the real numbers land. Replace
 * `PLACEHOLDER_FEE` — or the individual rows — with Amana's actual card.
 *
 * Idempotent: re-running updates the fee on an existing destination rather
 * than inserting a duplicate, which also covers the migration's note that a
 * unique index cannot stop two country-level rows on its own (NULL city_id
 * compares as distinct on both Postgres and SQLite).
 */
class AmanaDeliveryRateSeeder extends Seeder
{
    /** Stand-in until Amana's real per-destination pricing is supplied. */
    private const PLACEHOLDER_FEE = 20.00;

    /**
     * Where Hirfaty ships outside Morocco.
     *
     * Skewed to where the Moroccan diaspora and the craft export market
     * actually are, rather than trying to list every country on earth — a
     * destination with no row simply is not offered at checkout.
     *
     * @var array<string, string>
     */
    private const COUNTRIES = [
        'FR' => 'France',
        'ES' => 'Espagne',
        'BE' => 'Belgique',
        'NL' => 'Pays-Bas',
        'DE' => 'Allemagne',
        'IT' => 'Italie',
        'GB' => 'Royaume-Uni',
        'CH' => 'Suisse',
        'US' => 'États-Unis',
        'CA' => 'Canada',
        'AE' => 'Émirats arabes unis',
        'SA' => 'Arabie saoudite',
        'QA' => 'Qatar',
    ];

    public function run(): void
    {
        // Morocco: one rate per city, because that is how Amana prices
        // domestic delivery.
        foreach (City::all() as $city) {
            AmanaDeliveryRate::updateOrCreate(
                ['country_code' => 'MA', 'city_id' => $city->id],
                ['country_name' => 'Maroc', 'fee' => self::PLACEHOLDER_FEE],
            );
        }

        // Everywhere else: one rate for the whole country.
        foreach (self::COUNTRIES as $code => $name) {
            AmanaDeliveryRate::updateOrCreate(
                ['country_code' => $code, 'city_id' => null],
                ['country_name' => $name, 'fee' => self::PLACEHOLDER_FEE],
            );
        }
    }
}
