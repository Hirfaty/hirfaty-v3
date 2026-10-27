<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Database\Seeder;

/**
 * Amana's rate card: Morocco, city by city.
 *
 * Every city ships at the flat 40 MAD Amana charges for a basket, the rate the
 * platform has used since launch.
 *
 * Amana used to carry the international destinations too, at a 20 MAD
 * placeholder. DHL Express took them over with its own weight-priced card
 * (DhlDeliveryRateSeeder), and the placeholders were removed — see the
 * `load_dhl_express_rate_card` migration.
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

    public function run(): void
    {
        $amana = DeliveryService::firstOrCreate(
            ['slug' => 'amana'],
            [
                'name' => 'Amana',
                'sort_order' => 1,
            ]
        );

        // One rate per city, because that is how Amana prices domestic
        // delivery.
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
    }
}
