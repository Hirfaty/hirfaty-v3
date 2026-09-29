<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $this->category = Category::create([
        'slug' => 'poterie',
        'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
    ]);
    $this->city = City::create(['name' => 'Fès']);
});

/** @return list<list<string>> */
function exportedCsv($response): array
{
    $body = $response->streamedContent();

    expect(substr($body, 0, 3))->toBe("\xEF\xBB\xBF");

    return array_map(
        fn ($line) => str_getcsv($line, escape: ''),
        preg_split('/\R/', trim(substr($body, 3))),
    );
}

/** @return list<list<mixed>> */
function exportedXlsx($response): array
{
    $reader = new XlsxReader;
    $reader->open($response->getFile()->getPathname());

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

test('the csv export lists every user with their shop, never a super admin', function () {
    $seller = User::factory()->create(['role' => 'seller', 'first_name' => 'Fatima', 'phone_number' => '+212 600000000']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'type' => 'cooperative',
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
    ]);
    User::factory()->create(['role' => 'buyer', 'first_name' => 'Karim']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.users.export', ['format' => 'csv']))
        ->assertOk()
        ->assertDownload('utilisateurs-hirfaty-'.now()->format('Y-m-d').'.csv');

    $rows = exportedCsv($response);

    expect($rows[0])->toContain('Prénom', 'Email', 'Boutique')
        ->and($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck(5))->not->toContain('super_admin', 'Administrateur');

    $fatima = collect($rows)->firstWhere(1, 'Fatima');
    expect($fatima)->toContain('Vendeur', 'Atelier Fès', 'Coopérative', 'Fès')
        // A phone number keeps its leading `+`: it is not a formula.
        ->and($fatima[4])->toBe('+212 600000000');
});

test('the export follows the filters applied to the table', function () {
    User::factory()->create(['role' => 'buyer', 'first_name' => 'Karim']);
    User::factory()->create(['role' => 'professional_buyer', 'first_name' => 'Salma']);

    $rows = exportedCsv(
        $this->actingAs($this->admin)
            ->get(route('admin.users.export', ['format' => 'csv', 'role' => 'professional_buyer']))
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('Salma');
});

test('formula-like values are disarmed in the csv', function () {
    User::factory()->create(['role' => 'buyer', 'first_name' => '=HYPERLINK("http://evil")']);

    $rows = exportedCsv(
        $this->actingAs($this->admin)->get(route('admin.users.export', ['format' => 'csv']))
    );

    expect($rows[1][1])->toBe('\'=HYPERLINK("http://evil")');
});

test('the excel export is a readable xlsx with the same rows', function () {
    User::factory()->create(['role' => 'buyer', 'first_name' => '=1+1']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.users.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertDownload('utilisateurs-hirfaty-'.now()->format('Y-m-d').'.xlsx');

    $rows = exportedXlsx($response);

    expect($rows)->toHaveCount(2)
        ->and($rows[0][1])->toBe('Prénom')
        // Written as text, never as a live formula.
        ->and($rows[1][1])->toBe('=1+1');
});

test('an unknown format is refused', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.export', ['format' => 'pdf']))
        ->assertSessionHasErrors('format');
});

test('non admins cannot export users', function () {
    foreach (['seller', 'buyer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.users.export', ['format' => 'csv']))
            ->assertRoleDenied();
    }
});
