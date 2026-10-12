<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Descriptions are written in the rich-text editor and shown as HTML, so what
 * the admin typed — every line, blank line and space — is what buyers read.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);

    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes',
        'category_id' => Category::create([
            'slug' => 'poterie',
            'name' => ['fr' => 'Poterie', 'ar' => 'فخار', 'en' => 'Pottery'],
        ])->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
});

function saveDescription(string $description): ?string
{
    test()->actingAs(test()->admin)
        ->post(route('admin.products.store'), [
            'store_id' => test()->store->id,
            'name' => 'Vase',
            'status' => 'draft',
            'description' => $description,
        ])
        ->assertRedirect();

    return Product::firstWhere('name', 'Vase')->description;
}

test('a description keeps its lines, blank lines and spaces', function () {
    $html = '<p>description</p><p>new line</p><p></p><p>deux  espaces</p>';

    expect(saveDescription($html))->toBe($html);
});

test('a description is cut down to the allowlist before it is stored', function () {
    expect(saveDescription('<p onclick="x()">Beau <strong>vase</strong></p><script>alert(1)</script>'))
        ->toBe('<p>Beau <strong>vase</strong></p>');
});

test('an empty editor document is saved as no description', function () {
    expect(saveDescription('<p></p>'))->toBeNull();
});

test('the plain-text version keeps one line per paragraph', function () {
    expect(HtmlSanitizer::toText('<p>Laine &amp; coton</p><p></p><ul><li><p>Fait main</p></li></ul>'))
        ->toBe("Laine & coton\n\nFait main")
        ->and(HtmlSanitizer::toText('<p></p>'))->toBeNull();
});

test('existing plain-text descriptions become one escaped paragraph per line', function () {
    $product = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Ancien',
        'slug' => 'ancien',
        'status' => 'draft',
        'description' => "description\nnew line\n\n<b>pas du HTML</b>",
    ]);
    $alreadyHtml = Product::create([
        'store_id' => $this->store->id,
        'name' => 'Nouveau',
        'slug' => 'nouveau',
        'status' => 'draft',
        'description' => '<p>déjà &amp; converti</p>',
    ]);

    (require database_path('migrations/2026_10_12_120000_convert_product_descriptions_to_html.php'))->up();

    expect(DB::table('products')->where('id', $product->id)->value('description'))
        ->toBe('<p>description</p><p>new line</p><p></p><p>&lt;b&gt;pas du HTML&lt;/b&gt;</p>')
        ->and(DB::table('products')->where('id', $alreadyHtml->id)->value('description'))
        ->toBe('<p>déjà &amp; converti</p>');
});
