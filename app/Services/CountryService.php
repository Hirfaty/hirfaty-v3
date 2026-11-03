<?php

namespace App\Services;

use App\Models\Address;
use App\Models\City;
use Collator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Locale;

/**
 * Countries as a buyer reads them: named in the storefront's language, and
 * the one checkout opens on.
 *
 * `delivery_rates.country_name` is the French name — what the admin desk, the
 * back office and the emails read, the way `cities.name` is for a city. The
 * Arabic and English names are not stored: they come from ICU (PHP's intl),
 * keyed by the ISO code every rate row carries, so a country the desk adds is
 * translated without anyone typing it in three times.
 */
class CountryService
{
    /**
     * Where ICU's name is the formal one and a shop would use the short one:
     * "Hong Kong SAR China" is right on a passport and odd in a country list.
     *
     * @var array<string, array<string, string>>
     */
    private const SHORT_NAMES = [
        'HK' => ['fr' => 'Hong Kong', 'en' => 'Hong Kong', 'ar' => 'هونغ كونغ'],
        'MO' => ['fr' => 'Macao', 'en' => 'Macao', 'ar' => 'ماكاو'],
    ];

    public function __construct(private GeoLocationService $geo) {}

    /**
     * A country's name in a language — the visitor's when none is given.
     *
     * In French, the name on the rate card when there is one, so the buyer
     * reads the country the way the desk filed it. Otherwise ICU's, falling
     * back to the stored name (then the bare code) for a code ICU cannot name.
     */
    public function name(string $code, ?string $locale = null, ?string $stored = null): string
    {
        $code = strtoupper($code);
        $locale ??= app()->getLocale();
        $stored = filled($stored) ? $stored : null;

        if ($locale === City::SOURCE_LOCALE && $stored !== null) {
            return $stored;
        }

        if (isset(self::SHORT_NAMES[$code][$locale])) {
            return self::SHORT_NAMES[$code][$locale];
        }

        // ZZ is ICU's "Unknown Region", XA and XB its pseudo-locales: names, but not of countries.
        $name = class_exists(Locale::class) && ! in_array($code, ['ZZ', 'XA', 'XB'], true)
            ? Locale::getDisplayRegion('und_'.$code, $locale)
            : '';

        // ICU hands back the code itself for a region it has no name for.
        if ($name === '' || $name === false || strtoupper($name) === $code) {
            return $stored ?? $code;
        }

        return $name;
    }

    /**
     * Alphabetical by `name`, the way a reader of that language expects:
     * "États-Unis" with the Es, not after "Zimbabwe" as a byte-wise sort has it.
     *
     * @template T of array{name: string}
     *
     * @param  Collection<int, T>  $countries
     * @return Collection<int, T>
     */
    public function sortByName(Collection $countries, ?string $locale = null): Collection
    {
        $collator = class_exists(Collator::class) ? new Collator($locale ?? app()->getLocale()) : null;

        return $countries
            ->sort(fn (array $a, array $b) => $collator
                ? $collator->compare($a['name'], $b['name'])
                : strcasecmp($a['name'], $b['name']))
            ->values();
    }

    /**
     * The country a new delivery address opens on: the visitor's own, when
     * checkout ships there, else Morocco.
     *
     * Only a starting point for a blank form — a saved address keeps its own
     * country, and whatever the buyer picks wins. Detected the same way as
     * the display currency (GeoLocationService), so it shares that service's
     * caveats about VPNs and travellers, and its `currency.geo` switch.
     *
     * @param  array<int, string>  $offered  ISO codes checkout ships to
     */
    public function preselectFor(Request $request, array $offered): string
    {
        $offered = array_map('strtoupper', $offered);
        $detected = $this->geo->countryFor($request);

        return $detected !== null && in_array($detected, $offered, true)
            ? $detected
            : Address::DEFAULT_COUNTRY;
    }
}
