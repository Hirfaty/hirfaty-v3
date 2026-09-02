<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $seller = User::factory()->create(['role' => 'seller']);
    $this->store = Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier',
        'slug' => 'atelier-'.Str::random(5),
        'status' => 'active',
    ]);
    $this->category = Category::create(['name' => 'Tapis', 'slug' => 'tapis']);
});

/** One variant payload: options given as [type => value]. */
function axisVariant(array $options, int $stock = 5, ?int $id = null): array
{
    $payload = [
        'stock' => $stock,
        'options' => collect($options)
            ->map(fn ($value, $type) => ['type' => $type, 'value' => $value])
            ->values()
            ->all(),
    ];

    if ($id !== null) {
        $payload['id'] = $id;
    }

    return $payload;
}

function axisProduct(array $variants, array $overrides = []): array
{
    return array_merge([
        'name' => 'Tapis berbère',
        'store_id' => test()->store->id,
        'category_id' => test()->category->id,
        'base_price' => 100,
        'status' => 'published',
        'variants' => $variants,
    ], $overrides);
}

test('the axis is stored separately from the value', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            axisVariant(['color' => 'Rouge', 'dimension' => '4m']),
            axisVariant(['color' => 'Bleu', 'dimension' => '4m']),
        ]))
        ->assertSessionHasNoErrors();

    $option = ProductVariantOption::where('type', 'color')->first();

    // The bug this replaces stored name='Rouge', value='Rouge' and never
    // recorded the axis at all.
    expect($option->name)->toBe('Couleur')
        ->and($option->value)->toBe('Rouge');

    expect(ProductVariantOption::where('type', 'dimension')->first()->name)
        ->toBe('Dimension');
});

test('the canonical axis name is forced, not taken from the request', function () {
    $payload = axisProduct([
        axisVariant(['color' => 'Rouge']),
        axisVariant(['color' => 'Bleu']),
    ]);
    // A caller trying to name the axis themselves would otherwise split one
    // selector row into several the storefront cannot reconcile.
    $payload['variants'][0]['options'][0]['name'] = 'couleur';
    $payload['variants'][1]['options'][0]['name'] = 'Color';

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(ProductVariantOption::pluck('name')->unique()->values()->all())
        ->toBe(['Couleur']);
});

test('an "other" axis keeps the name the seller chose', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            [
                'stock' => 3,
                'options' => [['type' => 'other', 'name' => 'Tressage', 'value' => 'Serré']],
            ],
            [
                'stock' => 3,
                'options' => [['type' => 'other', 'name' => 'Tressage', 'value' => 'Lâche']],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(ProductVariantOption::pluck('name')->unique()->values()->all())->toBe(['Tressage'])
        ->and(ProductVariantOption::pluck('value')->all())->toBe(['Serré', 'Lâche']);
});

test('variants using different axes are rejected', function () {
    // Exactly the shape the old admin produced: one variant on Couleur,
    // another on Dimension, so no variant answers both selector rows.
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            axisVariant(['color' => 'Rouge']),
            axisVariant(['dimension' => 'XL']),
        ]))
        ->assertSessionHasErrors('variants.1.options');

    expect(Product::count())->toBe(0);
});

test('duplicate combinations are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            axisVariant(['color' => 'Rouge', 'dimension' => '4m']),
            axisVariant(['color' => 'Rouge', 'dimension' => '4m']),
        ]))
        ->assertSessionHasErrors('variants.1.options');

    expect(Product::count())->toBe(0);
});

test('duplicate detection ignores option order and casing', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            axisVariant(['color' => 'Rouge', 'dimension' => '4m']),
            axisVariant(['dimension' => '4m', 'color' => 'rouge']),
        ]))
        ->assertSessionHasErrors('variants.1.options');
});

test('a sparse grid is allowed', function () {
    // Rouge comes in 4m and 6m, Bleu only in 4m — the case that motivated
    // dependent selection on the storefront.
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), axisProduct([
            axisVariant(['color' => 'Rouge', 'dimension' => '4m']),
            axisVariant(['color' => 'Rouge', 'dimension' => '6m']),
            axisVariant(['color' => 'Bleu', 'dimension' => '4m']),
        ]))
        ->assertSessionHasNoErrors();

    expect(ProductVariant::count())->toBe(3);
});

test('editing keeps each variant on its own id', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), axisProduct([
        axisVariant(['color' => 'Rouge'], stock: 1),
        axisVariant(['color' => 'Bleu'], stock: 2),
        axisVariant(['color' => 'Vert'], stock: 3),
    ]));

    $product = Product::first();
    [$rouge, $bleu, $vert] = ProductVariant::orderBy('position')->get()->all();

    // Drop the FIRST variant and resave the other two, sending their ids.
    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), axisProduct([
            axisVariant(['color' => 'Bleu'], stock: 2, id: $bleu->id),
            axisVariant(['color' => 'Vert'], stock: 3, id: $vert->id),
        ]))
        ->assertSessionHasNoErrors();

    /*
     * The old position-based matching would have overwritten Rouge's row with
     * Bleu's data and deleted Vert's — silently repointing any cart or order
     * line that referenced those ids at a different colour.
     */
    expect(ProductVariant::find($rouge->id))->toBeNull()
        ->and(ProductVariant::find($bleu->id)->options->first()->value)->toBe('Bleu')
        ->and(ProductVariant::find($vert->id)->options->first()->value)->toBe('Vert')
        ->and(ProductVariant::count())->toBe(2);
});

test('a variant id belonging to another product is rejected', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), axisProduct([
        axisVariant(['color' => 'Rouge']),
    ]));
    $foreignVariant = ProductVariant::first();

    $this->actingAs($this->admin)->post(route('admin.products.store'), axisProduct([
        axisVariant(['color' => 'Bleu']),
    ], ['name' => 'Autre produit']));
    $other = Product::where('name', 'Autre produit')->first();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $other), axisProduct([
            axisVariant(['color' => 'Bleu'], id: $foreignVariant->id),
        ], ['name' => 'Autre produit']))
        ->assertSessionHasErrors('variants.0.id');

    // The other product's variant is untouched.
    expect(ProductVariant::find($foreignVariant->id)->product_id)
        ->not->toBe($other->id);
});
