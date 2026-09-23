<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The two delivery switches the admin desk controls from the delivery-rates
 * page.
 *
 *   - `showsDestinations()` — whether checkout offers a country and a
 *     Moroccan city at all. On (the default), delivery is priced from the
 *     rate card per city / per country, exactly as before this setting
 *     existed. Off, checkout ships to Morocco only, asks for no city, and
 *     every basket pays `flatFee()`.
 *   - `flatFee()` — that single fee, in MAD.
 *
 * A missing row means the default, so a fresh database behaves the way the
 * platform always has without anything being seeded.
 */
class DeliverySettingsService
{
    public const SHOW_DESTINATIONS_KEY = 'delivery.show_destinations';

    public const FLAT_FEE_KEY = 'delivery.flat_fee';

    /** Amana's domestic rate, which the platform has charged since launch. */
    public const DEFAULT_FLAT_FEE = 40.00;

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function showsDestinations(): bool
    {
        $value = $this->value(self::SHOW_DESTINATIONS_KEY);

        return $value === null ? true : $value === '1';
    }

    public function flatFee(): float
    {
        $value = $this->value(self::FLAT_FEE_KEY);

        return $value === null ? self::DEFAULT_FLAT_FEE : (float) $value;
    }

    public function update(float $flatFee, bool $showDestinations): void
    {
        Setting::updateOrCreate(
            ['key' => self::FLAT_FEE_KEY],
            ['value' => number_format($flatFee, 2, '.', '')],
        );

        Setting::updateOrCreate(
            ['key' => self::SHOW_DESTINATIONS_KEY],
            ['value' => $showDestinations ? '1' : '0'],
        );

        $this->values = null;
    }

    /**
     * Both keys in one query, memoised for the life of this instance: a
     * single checkout reads them from the request validation, the fee quote
     * and the order write.
     */
    private function value(string $key): ?string
    {
        $this->values ??= Setting::query()
            ->whereIn('key', [self::SHOW_DESTINATIONS_KEY, self::FLAT_FEE_KEY])
            ->pluck('value', 'key')
            ->all();

        return $this->values[$key] ?? null;
    }
}
