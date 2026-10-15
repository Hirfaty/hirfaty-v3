<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Store;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The desk's private notes on /admin/users/{id}. Admins write and read them;
 * the account holder — and every other non-admin — never can.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'super_admin',
        'first_name' => 'Salma',
        'last_name' => 'Desk',
    ]);
    $this->buyer = User::factory()->create(['role' => 'buyer']);
});

test('an admin can add a note to an account', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.notes.store', $this->buyer), ['body' => "  Rappelé le 12/10.\nPréfère WhatsApp.  "])
        ->assertRedirect()
        ->assertSessionHas('success');

    $note = UserNote::sole();
    expect($note->user_id)->toBe($this->buyer->id)
        ->and($note->author_id)->toBe($this->admin->id)
        ->and($note->body)->toBe("Rappelé le 12/10.\nPréfère WhatsApp.");
});

test('a note needs a body and is capped at 2000 characters', function (string $body) {
    $this->actingAs($this->admin)
        ->post(route('admin.users.notes.store', $this->buyer), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(UserNote::count())->toBe(0);
})->with([
    'empty' => '',
    'blank' => '   ',
    'too long' => str_repeat('a', 2001),
]);

test('the buyer page lists the notes newest first with their author', function () {
    $this->buyer->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Première note']);
    $this->travel(1)->minutes();
    $this->buyer->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Deuxième note']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->buyer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/showBuyer')
            ->has('notes', 2)
            ->where('notes.0.body', 'Deuxième note')
            ->where('notes.0.author', 'Salma Desk')
            ->where('notes.1.body', 'Première note')
            // Its own prop — never loaded onto the serialised user.
            ->missing('user.admin_notes'));
});

test('the seller page carries the notes too', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    Store::create([
        'user_id' => $seller->id,
        'store_name' => 'Atelier Fès',
        'slug' => 'atelier-fes-'.Str::random(5),
        'category_id' => Category::create(['slug' => 'cuir', 'name' => ['fr' => 'Cuir', 'ar' => 'جلد', 'en' => 'Leather']])->id,
        'city_id' => City::create(['name' => 'Fès'])->id,
    ]);
    $seller->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Photos à refaire']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $seller))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/showSeller')
            ->has('notes', 1)
            ->where('notes.0.body', 'Photos à refaire'));
});

test('a note outlives the admin who wrote it', function () {
    $other = User::factory()->create(['role' => 'super_admin']);
    $this->buyer->adminNotes()->create(['author_id' => $other->id, 'body' => 'Écrit par un ancien admin']);
    $other->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->buyer))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notes', 1)
            ->where('notes.0.author', null));
});

test('an admin can delete a note', function () {
    $note = $this->buyer->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'À supprimer']);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.notes.destroy', [$this->buyer, $note]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(UserNote::count())->toBe(0);
});

test('a note cannot be deleted through another account', function () {
    $other = User::factory()->create(['role' => 'buyer']);
    $note = $other->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Note sur un autre compte']);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.notes.destroy', [$this->buyer, $note]))
        ->assertNotFound();

    expect(UserNote::count())->toBe(1);
});

test('notes go with the account they are about', function () {
    $this->buyer->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Compte de test']);

    $this->buyer->delete();

    expect(UserNote::count())->toBe(0);
});

test('non-admins can neither add nor delete notes', function (string $role) {
    $note = $this->buyer->adminNotes()->create(['author_id' => $this->admin->id, 'body' => 'Privée']);
    $intruder = $role === 'self' ? $this->buyer : User::factory()->create(['role' => $role]);

    $this->actingAs($intruder)
        ->post(route('admin.users.notes.store', $this->buyer), ['body' => 'Tentative'])
        ->assertRoleDenied();

    $this->actingAs($intruder)
        ->delete(route('admin.users.notes.destroy', [$this->buyer, $note]))
        ->assertRoleDenied();

    expect(UserNote::count())->toBe(1);
})->with(['self', 'buyer', 'seller']);

test('guests cannot add notes', function () {
    $this->post(route('admin.users.notes.store', $this->buyer), ['body' => 'Tentative'])
        ->assertRedirect(route('login'));

    expect(UserNote::count())->toBe(0);
});
