<?php

namespace App\Services\Cathedis;

use App\Models\CathedisCity;
use App\Models\CathedisSector;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Database\Seeders\CathedisDeliveryRateSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps Cathedis's city list in `cathedis_cities` / `cathedis_sectors` and
 * links every locality on our Cathedis card to the city Cathedis knows it by,
 * so a delivery can be created with the exact name Cathedis expects.
 *
 * The card's names were tidied from Cathedis's tariff sheet and differ from
 * its city list ("Dar Bouazza" is "DarBouazza", "Ksar El Kébir" is
 * "Ksar L'kbir"), so a locality is matched loosely — accents, case and
 * punctuation ignored — on the Cathedis name and on every alias Cathedis
 * resolves. A few are spelled too differently for that and are mapped by
 * hand in KNOWN; whatever is left is reported for the desk.
 *
 * Additive like the rate cards: a city already linked keeps its link, and a
 * Cathedis city gone from the list is archived, never deleted, so a link to it
 * survives.
 */
class CathedisCitySyncService
{
    /**
     * Localities of our card whose spelling no match can bridge, checked one by
     * one against Cathedis's list: our French name ⇒ Cathedis city id.
     *
     * Two are here even though they match, because the loose match lands on
     * the wrong city: Sidi Hajjaj (Casablanca) finds Sidi Hajjaj-BERRECHID
     * (Casablanca's is the Oued Hassar one), and Aïn Bni Mathar finds an alias
     * of Jerada rather than Cathedis's own Aïn-Béni-Mathar.
     */
    private const KNOWN = [
        'Aïn Bni Mathar' => 642,         // Aïn-Béni-Mathar (not Jerada's alias)
        'Aït Iaaza' => 493,              // AIT IAZZA
        'Aoufous' => 1019,               // AOUFOUS - ERRACHIDIA
        'Attaouia' => 176,               // ELATTAOUIA
        'Bdouza' => 57,                  // Bedouza
        'Belksiri' => 193,               // Mechraa belksiri
        'Ben Tayeb (Nador)' => 413,      // Ben Taieb
        'Béni Ensar' => 417,             // Beni Ansar (alias "Béni Ansar")
        'Chouiter' => 159,               // CHWITER
        'Chrafate (Chefchaouen)' => 701, // CHERAFAT
        'El Kebab (Khénifra)' => 628,    // EL KBAB
        'Gouassem' => 151,               // Lagouassem
        'Jamaat Fdala' => 736,           // JMAAT FDALATE
        'Kaf Nsour (Khénifra)' => 632,   // KAHF NSOUR
        'Kariat Arkman (Nador)' => 407,  // Kariat Arkmane
        'Kariat Ba Mohamed' => 861,      // Kariat Ba Mohammed- Fes région-
        'Kelâat M\'Gouna' => 209,        // KALAAT M'GOUNA
        'Keliaa' => 202,                 // LEQLIAA (alias "KLIAA")
        'Laaroui' => 416,                // Al Aaroui
        'Laayoun Charkia' => 645,        // Laâyoune Sidi Mellouk – Oujda
        'Lhaj Kaddour' => 93,            // El Haj Kaddour
        'Lmhaya' => 85,                  // Mhaya
        'Oulad Hamdane (El Jadida)' => 719, // Oulad Hamdane-El Jadida Région
        'Salé Al Jadida' => 11,          // Sala Al Jadida
        'Sebaa Aïoun' => 83,             // Sbaa ayoune
        'Sidi Addi' => 781,              // Sidi Aadi-Fes R
        'Sidi Ghiat' => 443,             // Sidi Abdellah Ghiat
        'Sidi Hajjaj (Casablanca)' => 931, // Sidi Hajjaj-ouad hassar casa-
        'Tamellalt' => 454,              // TAMALLALT
        'Tameslouht' => 156,             // TAMESLUHT
        'Taoujdate' => 84,               // Ain Taoujtate
        'Tleta Bouguedra' => 63,         // TLAT BOUGEDRA
        'Tnine Ghiat (Safi)' => 54,      // Tnin Laghyat (alias "TNINE EL GHIAT")
        'Zaouiat Sidi Smail' => 637,     // Sidi Smaïl
    ];

    public function __construct(private readonly CathedisClient $client) {}

    /**
     * Fetch Cathedis's list, store it, and link what can be linked.
     *
     * @return array{cities: int, sectors: int, linked: int, unmatched: list<string>, unserved: list<string>}
     *
     * @throws CathedisException
     */
    public function sync(): array
    {
        $payload = $this->client->cities();

        $counts = DB::transaction(fn () => $this->store($payload));

        return $counts + $this->link();
    }

    /**
     * Upsert Cathedis's cities and sectors; archive the cities it no longer
     * lists and drop the sectors it removed.
     *
     * @param  list<array<string, mixed>>  $payload
     * @return array{cities: int, sectors: int}
     */
    private function store(array $payload): array
    {
        $now = now();
        $cities = [];
        $sectors = [];

        foreach ($payload as $row) {
            if (! isset($row['id'], $row['name'])) {
                continue;
            }

            $cities[] = [
                'id' => (int) $row['id'],
                'name' => trim((string) $row['name']),
                'zone' => $row['zone'] ?? null,
                'aliases' => json_encode(
                    collect($row['aliases'] ?? [])->pluck('name')->filter()->map(fn ($n) => trim((string) $n))->values()->all(),
                    JSON_UNESCAPED_UNICODE,
                ),
                'delivers' => (bool) ($row['deliveryAvailability'] ?? false),
                'archived' => (bool) ($row['archived'] ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($row['sectors'] ?? [] as $sector) {
                // Cathedis's own test entries ("TEST", "test AFC almanjra").
                if (! isset($sector['id'], $sector['name']) || Str::startsWith(Str::lower(trim($sector['name'])), 'test')) {
                    continue;
                }

                $sectors[] = [
                    'id' => (int) $sector['id'],
                    'cathedis_city_id' => (int) $row['id'],
                    'name' => trim((string) $sector['name']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $cityIds = array_column($cities, 'id');

        foreach (array_chunk($cities, 200) as $chunk) {
            CathedisCity::upsert($chunk, ['id'], ['name', 'zone', 'aliases', 'delivers', 'archived', 'updated_at']);
        }

        CathedisCity::whereNotIn('id', $cityIds)->update(['archived' => true]);

        foreach (array_chunk($sectors, 500) as $chunk) {
            CathedisSector::upsert($chunk, ['id'], ['cathedis_city_id', 'name', 'updated_at']);
        }

        CathedisSector::whereIn('cathedis_city_id', $cityIds)
            ->whereNotIn('id', array_column($sectors, 'id'))
            ->delete();

        return ['cities' => count($cities), 'sectors' => count($sectors)];
    }

    /**
     * Link every locality of the Cathedis card that has no link yet.
     *
     * `unmatched` lists the localities still without one; `unserved` those
     * linked to a city Cathedis does not deliver to right now — both are the
     * desk's to settle with Cathedis.
     *
     * @return array{linked: int, unmatched: list<string>, unserved: list<string>}
     */
    private function link(): array
    {
        $service = DeliveryService::where('slug', CathedisDeliveryRateSeeder::SLUG)->first();

        if (! $service) {
            return ['linked' => 0, 'unmatched' => [], 'unserved' => []];
        }

        $cities = City::whereIn('id', DeliveryRate::where('delivery_service_id', $service->id)
            ->whereNotNull('city_id')
            ->select('city_id'))
            ->orderBy('name')
            ->get();

        $catalogue = CathedisCity::all()->keyBy('id');
        $index = $this->index($catalogue);
        $known = collect(self::KNOWN)->mapWithKeys(fn (int $id, string $name) => [$this->matchKey($name) => $id]);

        $linked = 0;
        $unmatched = [];
        $unserved = [];

        foreach ($cities as $city) {
            if ($city->cathedis_city_id === null) {
                $match = $this->resolve($city->name, $known, $index, $catalogue);

                if ($match !== null) {
                    $city->update(['cathedis_city_id' => $match]);
                    $linked++;
                }
            }

            if ($city->cathedis_city_id === null) {
                $unmatched[] = $city->name;
            } elseif (! $catalogue->get($city->cathedis_city_id)?->isServed()) {
                $unserved[] = $city->name;
            }
        }

        return ['linked' => $linked, 'unmatched' => $unmatched, 'unserved' => $unserved];
    }

    /**
     * The Cathedis city a locality is, or null.
     *
     * A name with a bracket is tried whole, then joined ("Ajdir (Taza)" as
     * "ajdirtaza", Cathedis's "AJDIR-TAZA"), then without it ("Akka (Tata)" as
     * "akka").
     *
     * @param  Collection<string, int>  $known
     * @param  array<string, list<array{id: int, exact: bool}>>  $index
     * @param  Collection<int, CathedisCity>  $catalogue
     */
    private function resolve(string $name, Collection $known, array $index, Collection $catalogue): ?int
    {
        $key = $this->matchKey($name);

        if ($known->has($key)) {
            return $catalogue->has($known[$key]) ? $known[$key] : null;
        }

        $keys = [$key];

        if (preg_match('/^(.*?)\s*\((.*)\)\s*$/u', $name, $m)) {
            $keys[] = $this->matchKey($m[1].$m[2]);
            $keys[] = $this->matchKey($m[1]);
        }

        foreach ($keys as $candidate) {
            if (isset($index[$candidate])) {
                return $this->pick($index[$candidate], $catalogue);
            }
        }

        return null;
    }

    /**
     * When one spelling finds several Cathedis cities (Cathedis lists Midelt
     * and Mirleft twice, and "ZAIO" is also an alias of Aklim), prefer the
     * city of that name over one that only has it as an alias, then one that
     * is served, then the oldest.
     *
     * @param  list<array{id: int, exact: bool}>  $candidates
     * @param  Collection<int, CathedisCity>  $catalogue
     */
    private function pick(array $candidates, Collection $catalogue): int
    {
        return collect($candidates)
            ->sortBy(fn (array $c) => [
                $c['exact'] ? 0 : 1,
                $catalogue[$c['id']]->isServed() ? 0 : 1,
                $c['id'],
            ])
            ->first()['id'];
    }

    /**
     * Every Cathedis name and alias, by match key.
     *
     * @param  Collection<int, CathedisCity>  $catalogue
     * @return array<string, list<array{id: int, exact: bool}>>
     */
    private function index(Collection $catalogue): array
    {
        $index = [];

        foreach ($catalogue as $city) {
            $index[$this->matchKey($city->name)][] = ['id' => $city->id, 'exact' => true];

            foreach ($city->aliases ?? [] as $alias) {
                $index[$this->matchKey($alias)][] = ['id' => $city->id, 'exact' => false];
            }
        }

        return $index;
    }

    /** "Aïn-Béni-Mathar" and "AIN BENI MATHAR" both become "ainbenimathar". */
    private function matchKey(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }
}
