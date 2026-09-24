<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

test('the staging host shares the production url so admin pages can warn', function () {
    $this->actingAs($this->admin)
        ->get('http://init.hirfaty.com/admin/products/create')
        ->assertInertia(fn (Assert $page) => $page
            ->where('staging.production_url', 'https://hirfaty.com'));
});

test('any other host gets no staging warning', function () {
    $this->actingAs($this->admin)
        ->get('http://hirfaty.com/admin/products/create')
        ->assertInertia(fn (Assert $page) => $page->where('staging', null));
});
