<?php

namespace App\Services;

use App\Models\City;
use Collator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Moroccan cities: what each one is called in the storefront's languages, the
 * lists the forms pick from, and the admin's view of where the shops are.
 *
 * `cities.name` is the French name — what a courier label, the desk's emails
 * and the back office read. Arabic and English sit beside it in
 * `name_translations`; see City::nameFor().
 */
class CityService
{
    /**
     * Arabic and English names for the Moroccan cities Hirfaty is likely to
     * deliver to or source from, keyed by the French name.
     *
     * The registry itself is not this list: it holds the cities the desk has
     * put on the rate card. This is only where their translations come from —
     * the migration that introduced them, CitySeeder, and a city the desk adds
     * from the delivery-rates page without typing the names in.
     *
     * @var array<string, array{ar: string, en: string}>
     */
    private const KNOWN_NAMES = [
        'Agadir' => ['ar' => 'أكادير', 'en' => 'Agadir'],
        'Al Hoceïma' => ['ar' => 'الحسيمة', 'en' => 'Al Hoceima'],
        'Asilah' => ['ar' => 'أصيلة', 'en' => 'Asilah'],
        'Azemmour' => ['ar' => 'أزمور', 'en' => 'Azemmour'],
        'Azilal' => ['ar' => 'أزيلال', 'en' => 'Azilal'],
        'Azrou' => ['ar' => 'أزرو', 'en' => 'Azrou'],
        'Ben Guerir' => ['ar' => 'ابن جرير', 'en' => 'Ben Guerir'],
        'Béni Mellal' => ['ar' => 'بني ملال', 'en' => 'Beni Mellal'],
        'Berkane' => ['ar' => 'بركان', 'en' => 'Berkane'],
        'Berrechid' => ['ar' => 'برشيد', 'en' => 'Berrechid'],
        'Boujdour' => ['ar' => 'بوجدور', 'en' => 'Boujdour'],
        'Bouznika' => ['ar' => 'بوزنيقة', 'en' => 'Bouznika'],
        'Casablanca' => ['ar' => 'الدار البيضاء', 'en' => 'Casablanca'],
        'Chefchaouen' => ['ar' => 'شفشاون', 'en' => 'Chefchaouen'],
        'Chichaoua' => ['ar' => 'شيشاوة', 'en' => 'Chichaoua'],
        'Dakhla' => ['ar' => 'الداخلة', 'en' => 'Dakhla'],
        'Demnate' => ['ar' => 'دمنات', 'en' => 'Demnate'],
        'El Jadida' => ['ar' => 'الجديدة', 'en' => 'El Jadida'],
        'Erfoud' => ['ar' => 'أرفود', 'en' => 'Erfoud'],
        'Errachidia' => ['ar' => 'الرشيدية', 'en' => 'Errachidia'],
        'Essaouira' => ['ar' => 'الصويرة', 'en' => 'Essaouira'],
        'Fès' => ['ar' => 'فاس', 'en' => 'Fez'],
        'Fnideq' => ['ar' => 'الفنيدق', 'en' => 'Fnideq'],
        'Fquih Ben Salah' => ['ar' => 'الفقيه بن صالح', 'en' => 'Fquih Ben Salah'],
        'Guelmim' => ['ar' => 'كلميم', 'en' => 'Guelmim'],
        'Guercif' => ['ar' => 'جرسيف', 'en' => 'Guercif'],
        'Ifrane' => ['ar' => 'إفران', 'en' => 'Ifrane'],
        'Inezgane' => ['ar' => 'إنزكان', 'en' => 'Inezgane'],
        'Kelâa des Sraghna' => ['ar' => 'قلعة السراغنة', 'en' => 'Kelaat Sraghna'],
        'Kénitra' => ['ar' => 'القنيطرة', 'en' => 'Kenitra'],
        'Khémisset' => ['ar' => 'الخميسات', 'en' => 'Khemisset'],
        'Khénifra' => ['ar' => 'خنيفرة', 'en' => 'Khenifra'],
        'Khouribga' => ['ar' => 'خريبكة', 'en' => 'Khouribga'],
        'Ksar El Kébir' => ['ar' => 'القصر الكبير', 'en' => 'Ksar El Kebir'],
        'Laâyoune' => ['ar' => 'العيون', 'en' => 'Laayoune'],
        'Larache' => ['ar' => 'العرائش', 'en' => 'Larache'],
        'Marrakech' => ['ar' => 'مراكش', 'en' => 'Marrakesh'],
        'Martil' => ['ar' => 'مرتيل', 'en' => 'Martil'],
        "M'diq" => ['ar' => 'المضيق', 'en' => "M'diq"],
        'Meknès' => ['ar' => 'مكناس', 'en' => 'Meknes'],
        'Midelt' => ['ar' => 'ميدلت', 'en' => 'Midelt'],
        'Mohammedia' => ['ar' => 'المحمدية', 'en' => 'Mohammedia'],
        'Nador' => ['ar' => 'الناظور', 'en' => 'Nador'],
        'Oualidia' => ['ar' => 'الوليدية', 'en' => 'Oualidia'],
        'Ouarzazate' => ['ar' => 'ورزازات', 'en' => 'Ouarzazate'],
        'Ouezzane' => ['ar' => 'وزان', 'en' => 'Ouezzane'],
        'Oujda' => ['ar' => 'وجدة', 'en' => 'Oujda'],
        'Rabat' => ['ar' => 'الرباط', 'en' => 'Rabat'],
        'Rissani' => ['ar' => 'الريصاني', 'en' => 'Rissani'],
        'Safi' => ['ar' => 'آسفي', 'en' => 'Safi'],
        'Salé' => ['ar' => 'سلا', 'en' => 'Salé'],
        'Sefrou' => ['ar' => 'صفرو', 'en' => 'Sefrou'],
        'Settat' => ['ar' => 'سطات', 'en' => 'Settat'],
        'Sidi Bennour' => ['ar' => 'سيدي بنور', 'en' => 'Sidi Bennour'],
        'Sidi Kacem' => ['ar' => 'سيدي قاسم', 'en' => 'Sidi Kacem'],
        'Sidi Slimane' => ['ar' => 'سيدي سليمان', 'en' => 'Sidi Slimane'],
        'Skhirat' => ['ar' => 'الصخيرات', 'en' => 'Skhirat'],
        'Smara' => ['ar' => 'السمارة', 'en' => 'Smara'],
        'Tamegroute' => ['ar' => 'تمكروت', 'en' => 'Tamegroute'],
        'Tan-Tan' => ['ar' => 'طانطان', 'en' => 'Tan-Tan'],
        'Tanger' => ['ar' => 'طنجة', 'en' => 'Tangier'],
        'Taourirt' => ['ar' => 'تاوريرت', 'en' => 'Taourirt'],
        'Taroudant' => ['ar' => 'تارودانت', 'en' => 'Taroudant'],
        'Taza' => ['ar' => 'تازة', 'en' => 'Taza'],
        'Témara' => ['ar' => 'تمارة', 'en' => 'Temara'],
        'Tétouan' => ['ar' => 'تطوان', 'en' => 'Tetouan'],
        'Tinghir' => ['ar' => 'تنغير', 'en' => 'Tinghir'],
        'Tiznit' => ['ar' => 'تيزنيت', 'en' => 'Tiznit'],
        'Youssoufia' => ['ar' => 'اليوسفية', 'en' => 'Youssoufia'],
        'Zagora' => ['ar' => 'زاكورة', 'en' => 'Zagora'],
    ];

    /** How many cities the admin's distribution chart ranks. */
    public const DISTRIBUTION_LIMIT = 10;

    /**
     * The Arabic and English names on file for a French city name, or an
     * empty array for a city this list does not know.
     *
     * Matched loosely — case, accents, spaces and hyphens aside — so "Fes",
     * "Beni-Mellal" or "laayoune" typed by the desk still find their entry.
     *
     * @return array<string, string>
     */
    public function knownTranslations(string $name): array
    {
        $key = $this->matchKey($name);

        foreach (self::KNOWN_NAMES as $french => $translations) {
            if ($this->matchKey($french) === $key) {
                return $translations;
            }
        }

        return [];
    }

    /**
     * What to store in `name_translations` for a city being added: the names
     * the desk typed, and the known ones for whatever it left blank.
     *
     * @param  array<string, string|null>  $typed  keyed by locale
     * @return array<string, string>|null null when there is nothing to store
     */
    public function translationsFor(string $name, array $typed = []): ?array
    {
        $typed = collect($typed)
            ->only(City::TRANSLATED_LOCALES)
            ->map(fn (?string $value) => trim((string) $value))
            ->filter()
            ->all();

        $translations = $typed + $this->knownTranslations($name);

        return $translations === [] ? null : $translations;
    }

    /**
     * The cities a form offers, named and sorted for the visitor's language.
     *
     * @param  array<int, int>|null  $ids  only these cities; null for all of them
     * @return Collection<int, array{id: int, name: string}>
     */
    public function options(?array $ids = null): Collection
    {
        $cities = City::query()
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->get(['id', 'name', 'name_translations'])
            ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->translated_name]);

        return $this->sortByName($cities, app()->getLocale());
    }

    /**
     * Everything the admin cities page shows: each city with its names and
     * its number of shops, and the cities ranked by shops for the chart.
     *
     * Read-only. Cities enter the registry from the delivery-rates page, which
     * is also where the desk decides whether one can be delivered to.
     *
     * @return array{
     *     cities: Collection<int, array{id: int, name: string, region: string|null, translations: array<string, string|null>, stores_count: int}>,
     *     distribution: Collection<int, array{id: int, name: string, stores_count: int}>,
     *     totalStores: int,
     * }
     */
    public function adminIndex(): array
    {
        $cities = $this->sortByName(
            City::withCount('stores')->get()->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'region' => $city->region,
                'translations' => collect(City::TRANSLATED_LOCALES)
                    ->mapWithKeys(fn (string $locale) => [
                        $locale => $city->name_translations[$locale] ?? null,
                    ])
                    ->all(),
                'stores_count' => (int) $city->stores_count,
            ]),
            City::SOURCE_LOCALE,
        );

        // PHP's sort is stable, so cities with the same count stay in
        // alphabetical order instead of swapping places between two visits.
        $distribution = $cities
            ->filter(fn (array $city) => $city['stores_count'] > 0)
            ->sortByDesc('stores_count')
            ->take(self::DISTRIBUTION_LIMIT)
            ->map(fn (array $city) => [
                'id' => $city['id'],
                'name' => $city['name'],
                'stores_count' => $city['stores_count'],
            ])
            ->values();

        return [
            'cities' => $cities,
            'distribution' => $distribution,
            'totalStores' => (int) $cities->sum('stores_count'),
        ];
    }

    /**
     * Alphabetical by `name`, the way a reader of that language expects:
     * "Béni Mellal" with the Bs, not after the Zs as a byte-wise sort has it.
     *
     * @template T of array{name: string}
     *
     * @param  Collection<int, T>  $cities
     * @return Collection<int, T>
     */
    private function sortByName(Collection $cities, string $locale): Collection
    {
        $collator = class_exists(Collator::class) ? new Collator($locale) : null;

        return $cities
            ->sort(fn (array $a, array $b) => $collator
                ? $collator->compare($a['name'], $b['name'])
                : strcasecmp($a['name'], $b['name']))
            ->values();
    }

    /** "Béni-Mellal" and "beni mellal" both become "benimellal". */
    private function matchKey(string $name): string
    {
        return (string) preg_replace('/[^a-z]/', '', Str::lower(Str::ascii($name)));
    }
}
