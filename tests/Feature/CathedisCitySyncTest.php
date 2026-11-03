<?php

use App\Models\CathedisCity;
use App\Models\CathedisSector;
use App\Models\City;
use App\Models\DeliveryRate;
use App\Models\DeliveryService;
use Illuminate\Support\Facades\Http;

/*
 * `cathedis:sync-cities`: Cathedis's public city list stored locally, and the
 * localities of our Cathedis card linked to the city Cathedis knows them by.
 */

const CATHEDIS_CITY_URL = 'https://api.cathedis.delivery/ws/public/c2c/city';

// Pinned, so a CATHEDIS_API_URL in a local .env never reaches these tests.
beforeEach(fn () => config(['services.cathedis.url' => 'https://api.cathedis.delivery']));

/** A city as Cathedis's endpoint lists it, trimmed to the fields we read. */
function cathedisRow(int $id, string $name, array $aliases = [], array $sectors = [], bool $delivers = true, bool $archived = false): array
{
    return [
        'id' => $id,
        'name' => $name,
        'zone' => 'SMALL_CITIES',
        'aliases' => array_map(fn (string $alias) => ['name' => $alias, 'id' => crc32($alias)], $aliases),
        'sectors' => array_map(fn (array $s) => ['id' => $s[0], 'name' => $s[1]], $sectors),
        'deliveryAvailability' => $delivers,
        'archived' => $archived,
        'createdBy' => ['code' => 'someone', 'fullName' => 'Cathedis Agent'],
    ];
}

/**
 * Cathedis's answers to successive syncs: a list of rows each, or a ready
 * response for a failure. (A later Http::fake would not replace the first.)
 */
function fakeCathedisCities(array ...$answers): void
{
    $sequence = Http::sequence();

    foreach ($answers as $answer) {
        $sequence->pushResponse($answer['response'] ?? Http::response(['status' => 0, 'data' => $answer]));
    }

    Http::fake([CATHEDIS_CITY_URL => $sequence]);
}

/** Our localities on a Cathedis card, by French name. */
function cathedisCard(array $names): void
{
    $service = DeliveryService::create(['slug' => 'cathedis', 'name' => 'Cathedis']);

    foreach ($names as $name) {
        DeliveryRate::create([
            'delivery_service_id' => $service->id,
            'country_code' => 'MA',
            'country_name' => 'Maroc',
            'city_id' => City::create(['name' => $name])->id,
            'fee' => 45,
        ]);
    }
}

function cathedisLink(string $name): ?int
{
    return City::where('name', $name)->value('cathedis_city_id');
}

it('stores the list and links localities by name, alias and bracket', function () {
    cathedisCard(['Dar Bouazza', 'Akka (Tata)', 'Ajdir (Taza)', 'Fès']);
    fakeCathedisCities([
        cathedisRow(15, 'DarBouazza', ['DAR BOUAZZA'], [[804, 'DarBouazza'], [2884, 'Autre']]),
        cathedisRow(762, 'Akka', ['Akka'], [[3420, 'Akka'], [3569, 'Autre']]),
        cathedisRow(972, 'AJDIR-TAZA', [], [[4662, 'AUTRE']]),
        cathedisRow(19, 'Fes', ['FÈS', 'Fés'], [[1604, 'Fes'], [3010, 'TEST']]),
    ]);

    $this->artisan('cathedis:sync-cities')->assertSuccessful();

    expect(CathedisCity::count())->toBe(4)
        ->and(CathedisCity::find(15)->aliases)->toBe(['DAR BOUAZZA'])
        // Cathedis's own test sector is not kept.
        ->and(CathedisSector::pluck('name')->all())->not->toContain('TEST')
        ->and(cathedisLink('Dar Bouazza'))->toBe(15)
        ->and(cathedisLink('Akka (Tata)'))->toBe(762)
        ->and(cathedisLink('Ajdir (Taza)'))->toBe(972)
        ->and(cathedisLink('Fès'))->toBe(19);
});

it('settles a spelling shared by two Cathedis cities', function () {
    cathedisCard(['Midelt', 'Zaio']);
    fakeCathedisCities([
        cathedisRow(634, 'MIDELT'),
        cathedisRow(109, 'MIDELT'),
        // "ZAIO" is also an alias of Aklim: the city of that name wins.
        cathedisRow(611, 'Aklim', ['ZAIO']),
        cathedisRow(406, 'Zaio'),
    ]);

    $this->artisan('cathedis:sync-cities')->assertSuccessful();

    expect(cathedisLink('Midelt'))->toBe(109)
        ->and(cathedisLink('Zaio'))->toBe(406);
});

it('uses the hand-checked map before any loose match', function () {
    cathedisCard(['Sidi Hajjaj (Casablanca)', 'Tleta Bouguedra']);
    fakeCathedisCities([
        cathedisRow(73, 'Sidi Hajjaj-BERRECHID', ['Sidi Hajjaj']),
        cathedisRow(931, 'Sidi Hajjaj-ouad hassar casa-', ['OUED HASSAR']),
        cathedisRow(63, 'TLAT BOUGEDRA', ['TLAT BOUGUEDRA']),
    ]);

    $this->artisan('cathedis:sync-cities')->assertSuccessful();

    expect(cathedisLink('Sidi Hajjaj (Casablanca)'))->toBe(931)
        ->and(cathedisLink('Tleta Bouguedra'))->toBe(63);
});

it('reports what it could not link and what Cathedis does not serve', function () {
    cathedisCard(['Imgon (Tinghir)', 'Talmest']);
    fakeCathedisCities([cathedisRow(65, 'Talmest-SAFI RG-', ['Talmest'], delivers: false)]);

    $this->artisan('cathedis:sync-cities')
        ->expectsOutputToContain('not deliver to right now')
        ->expectsOutputToContain('- Talmest')
        ->expectsOutputToContain('not found')
        ->expectsOutputToContain('- Imgon (Tinghir)')
        ->assertSuccessful();

    expect(cathedisLink('Talmest'))->toBe(65)
        ->and(cathedisLink('Imgon (Tinghir)'))->toBeNull();
});

it('keeps existing links, archives dropped cities and removes dropped sectors', function () {
    cathedisCard(['Mirleft']);
    fakeCathedisCities(
        [
            cathedisRow(117, 'Mirleft', [], [[1227, 'Mirleft'], [2892, 'Autre']]),
            cathedisRow(619, 'MIRLEFT'),
        ],
        // Next sync: Cathedis dropped 117's "Mirleft" sector and all of 619.
        [cathedisRow(117, 'Mirleft', [], [[2892, 'Autre']])],
    );
    $this->artisan('cathedis:sync-cities')->assertSuccessful();

    // The desk relinks it to the other Mirleft before the next sync.
    City::where('name', 'Mirleft')->update(['cathedis_city_id' => 619]);
    $this->artisan('cathedis:sync-cities')->assertSuccessful();

    expect(cathedisLink('Mirleft'))->toBe(619)
        ->and(CathedisCity::find(619)->archived)->toBeTrue()
        ->and(CathedisSector::where('cathedis_city_id', 117)->pluck('id')->all())->toBe([2892]);
});

it('leaves the stored list alone when Cathedis fails', function () {
    cathedisCard(['Fès']);
    fakeCathedisCities(
        [cathedisRow(19, 'Fes')],
        // An empty list would archive every city: refused like an error.
        ['response' => Http::response(['status' => 0, 'data' => []])],
        ['response' => Http::response(['status' => -1, 'data' => [cathedisRow(19, 'Fes')]])],
        ['response' => Http::response('down', 503)],
    );

    $this->artisan('cathedis:sync-cities')->assertSuccessful();
    $this->artisan('cathedis:sync-cities')->assertFailed();
    $this->artisan('cathedis:sync-cities')->assertFailed();
    $this->artisan('cathedis:sync-cities')->assertFailed();

    expect(CathedisCity::find(19)->archived)->toBeFalse()
        ->and(cathedisLink('Fès'))->toBe(19);
});
