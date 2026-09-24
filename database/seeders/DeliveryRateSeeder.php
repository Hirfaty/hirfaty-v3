<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Database\Seeder;

/**
 * A delivery service's rate card.
 *
 * ⚠ FIGURES. Morocco is real: every city ships at the flat 40 MAD Amana
 * charges for a basket, the rate the platform has used since launch. Below it
 * there was no cheaper tier behind the 20 MAD placeholder — that figure was
 * the pre-rate-card flat fee, kept as a stand-in so the per-destination rows
 * could land without changing what a buyer saw. Now that the card is live,
 * Morocco is 40.
 *
 * International rows are still PLACEHOLDERS at the old flat fee: they are
 * only here so the diaspora/export checkout keeps working while the real
 * Amana card for abroad lands (or a dedicated international carrier such as
 * DHL takes the countries over). Replace `INTERNATIONAL_PLACEHOLDER_FEE` — or
 * the individual rows — with real figures once known.
 *
 * Idempotent, and additive only: re-running inserts destinations that are
 * missing and leaves existing rows alone. Fees are editable from the admin
 * delivery-rates page, so overwriting them here would silently undo the
 * desk's changes on the next seed (a destination the desk removed does come
 * back, though — seed once, then manage the card from the admin). Matching on
 * the destination also covers the migration's note that a unique index cannot
 * stop two country-level rows on its own (NULL city_id compares as distinct
 * on both Postgres and SQLite).
 */
class DeliveryRateSeeder extends Seeder
{
    /** What Amana charges to deliver one basket anywhere in Morocco, per city. */
    private const MOROCCO_FEE = 40.00;

    /** Stand-in until real international figures (and carrier) are supplied. */
    private const INTERNATIONAL_PLACEHOLDER_FEE = 20.00;

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
        $amana = DeliveryService::firstOrCreate(
            ['slug' => 'amana'],
            [
                'name' => 'Amana',
                'sort_order' => 1,
            ]
        );

        // Morocco: one rate per city, because that is how Amana prices
        // domestic delivery.
        foreach (City::all() as $city) {
            DeliveryRate::firstOrCreate(
                [
                    'delivery_service_id' => $amana->id,
                    'country_code' => 'MA',
                    'city_id' => $city->id,
                ],
                ['country_name' => 'Maroc', 'fee' => self::MOROCCO_FEE],
            );
        }

        // Everywhere else: one rate for the whole country. Placeholder fee —
        // see the class docblock.
        foreach (self::COUNTRIES as $code => $name) {
            DeliveryRate::firstOrCreate(
                [
                    'delivery_service_id' => $amana->id,
                    'country_code' => $code,
                    'city_id' => null,
                ],
                [
                    'country_name' => $name,
                    'fee' => self::INTERNATIONAL_PLACEHOLDER_FEE,
                ],
            );
        }
    }
}
