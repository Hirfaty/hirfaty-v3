<?php

use App\Models\BrochureLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * The back-office list of brochure requests. Read-only, so what matters is who
 * may see it and that the filters narrow the right rows.
 */
function lead(array $overrides = []): BrochureLead
{
    static $n = 0;
    $n++;

    return BrochureLead::create(array_merge([
        'first_name' => "Person{$n}",
        'last_name' => 'Test',
        'email' => "person{$n}@example.com",
        'type' => 'buyer',
    ], $overrides));
}

test('super admin can view the brochure leads', function () {
    lead(['first_name' => 'Amina']);

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->get(route('admin.brochure-leads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/brochure-leads/index')
            ->where('leads.data.0.first_name', 'Amina')
            ->where('counts.all', 1)
        );
});

test('the list is filtered by type', function () {
    lead(['type' => 'buyer']);
    lead(['type' => 'seller', 'seller_type' => 'cooperative']);

    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->get(route('admin.brochure-leads.index', ['type' => 'seller']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.seller_type', 'cooperative')
            // The tab counts deliberately ignore the active filter, so the
            // other tabs never read 0 just because this one is selected.
            ->where('counts.all', 2)
            ->where('counts.buyer', 1)
        );
});

test('the list is searchable by name and email', function () {
    lead(['first_name' => 'Amina', 'email' => 'amina@example.com']);
    lead(['first_name' => 'Youssef', 'email' => 'youssef@example.com']);

    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.brochure-leads.index', ['search' => 'Amina']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('leads.data', 1));

    $this->actingAs($admin)
        ->get(route('admin.brochure-leads.index', ['search' => 'youssef@']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.first_name', 'Youssef')
        );
});

/**
 * A signed-in user with the wrong role is redirected to the shop rather than
 * shown a 403 — see `RoleMiddleware`, which only aborts for a visitor who is
 * not signed in at all. Asserted as it behaves, not as one might expect.
 */
test('a seller is turned away from the brochure leads', function () {
    $this->actingAs(User::factory()->create(['role' => 'seller']))
        ->get(route('admin.brochure-leads.index'))
        ->assertRedirect(route('products.index'));
});

test('a buyer is turned away from the brochure leads', function () {
    $this->actingAs(User::factory()->create(['role' => 'buyer']))
        ->get(route('admin.brochure-leads.index'))
        ->assertRedirect(route('products.index'));
});

test('a visitor who is not signed in cannot see the brochure leads', function () {
    $this->get(route('admin.brochure-leads.index'))->assertRedirect(route('login'));
});
