<?php

use App\Models\BrochureLead;
use App\Models\User;
use App\Services\BrochureLeadService;
use Illuminate\Support\Facades\Storage;

/**
 * The homepage brochure: the form that unlocks it, and the gate on the file.
 *
 * The gate is the part worth the most coverage. The whole arrangement exists so
 * that the PDF is handed over in exchange for a name and an address — if the
 * download answers without the form having been sent, the form is decoration.
 */
beforeEach(function () {
    // `local` is the real disk in tests too, and the PDF is gitignored, so
    // without this every test would fall down the "file missing" branch.
    Storage::fake('local');
    Storage::disk('local')->put(BrochureLeadService::PATH, 'pretend-pdf');
});

/** A complete, valid submission — overridden per test. */
function brochurePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Amina',
        'last_name' => 'Benali',
        'email' => 'amina@example.com',
        'type' => 'buyer',
    ], $overrides);
}

it('records a lead from a visitor who is not signed in', function () {
    $response = $this->post('/brochure', brochurePayload());

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $lead = BrochureLead::sole();

    expect($lead->first_name)->toBe('Amina')
        ->and($lead->last_name)->toBe('Benali')
        ->and($lead->email)->toBe('amina@example.com')
        ->and($lead->type)->toBe('buyer')
        ->and($lead->downloads_count)->toBe(1);
});

it('stores the address lower-cased and trimmed', function () {
    $this->post('/brochure', brochurePayload([
        'email' => '  Amina@Example.COM  ',
    ]));

    expect(BrochureLead::pluck('email')->all())->toBe(['amina@example.com']);
});

it('keeps a seller\'s type', function () {
    $this->post('/brochure', brochurePayload([
        'type' => 'seller',
        'seller_type' => 'cooperative',
    ]));

    expect(BrochureLead::sole()->seller_type)->toBe('cooperative');
});

/**
 * The dialog hides the seller question the moment someone switches back to
 * "buyer", but whatever they had already chosen is still in the form state and
 * is posted along with the rest. Storing it would mean the column answers a
 * question this person was never asked.
 */
it('drops a seller type that arrives alongside type=buyer', function () {
    $this->post('/brochure', brochurePayload([
        'type' => 'buyer',
        'seller_type' => 'company',
    ]));

    expect(BrochureLead::sole()->seller_type)->toBeNull();
});

it('requires a seller to say which kind they are', function () {
    $response = $this->post('/brochure', brochurePayload([
        'type' => 'seller',
    ]));

    $response->assertSessionHasErrors('seller_type', errorBag: 'brochure');

    expect(BrochureLead::count())->toBe(0);
});

it('rejects a seller type that is not one of the known ones', function () {
    $response = $this->post('/brochure', brochurePayload([
        'type' => 'seller',
        'seller_type' => 'wholesaler',
    ]));

    $response->assertSessionHasErrors('seller_type', errorBag: 'brochure');
});

it('rejects a type that is neither buyer nor seller', function () {
    $response = $this->post('/brochure', brochurePayload([
        'type' => 'super_admin',
    ]));

    $response->assertSessionHasErrors('type', errorBag: 'brochure');
});

it('rejects a malformed address without storing anything', function () {
    $response = $this->post('/brochure', brochurePayload([
        'email' => 'not-an-email',
    ]));

    $response->assertSessionHasErrors('email', errorBag: 'brochure');

    expect(BrochureLead::count())->toBe(0);
});

it('requires both names', function () {
    $response = $this->post('/brochure', brochurePayload([
        'first_name' => '',
        'last_name' => '',
    ]));

    $response->assertSessionHasErrors(['first_name', 'last_name'], errorBag: 'brochure');
});

/**
 * Errors have to stay out of the default bag: the homepage carries the footer's
 * newsletter signup too, and both forms have a field called `email`.
 */
it('keeps its validation errors out of the default bag', function () {
    $response = $this->post('/brochure', brochurePayload([
        'email' => 'not-an-email',
    ]));

    $response->assertSessionHasErrorsIn('brochure', ['email']);
    $response->assertSessionDoesntHaveErrors('email');
});

// ── One person, one row ──────────────────────────────────────────────────────

it('does not create a second row when the same mailbox comes back', function () {
    $this->post('/brochure', brochurePayload());
    $response = $this->post('/brochure', brochurePayload([
        'email' => 'AMINA@example.com',
    ]));

    $response->assertSessionHas('success');
    $response->assertSessionHasNoErrors();

    expect(BrochureLead::count())->toBe(1);
});

it('counts a repeat request instead of losing it', function () {
    $this->post('/brochure', brochurePayload());
    $this->post('/brochure', brochurePayload());
    $this->post('/brochure', brochurePayload());

    expect(BrochureLead::sole()->downloads_count)->toBe(3);
});

it('refreshes the details when someone comes back having changed side', function () {
    $this->post('/brochure', brochurePayload());
    $this->post('/brochure', brochurePayload([
        'last_name' => 'Benali-Idrissi',
        'type' => 'seller',
        'seller_type' => 'artist',
    ]));

    $lead = BrochureLead::sole();

    expect($lead->last_name)->toBe('Benali-Idrissi')
        ->and($lead->type)->toBe('seller')
        ->and($lead->seller_type)->toBe('artist');
});

it('records the locale the visitor was reading in', function () {
    $this->withSession(['locale' => 'ar'])->post('/brochure', brochurePayload());

    expect(BrochureLead::sole()->locale)->toBe('ar');
});

// ── The gate ─────────────────────────────────────────────────────────────────

it('refuses the download to someone who has not sent the form', function () {
    $this->get('/brochure/download')->assertForbidden();
});

it('serves the file once the form has been sent', function () {
    $this->post('/brochure', brochurePayload());

    $response = $this->get('/brochure/download');

    $response->assertOk();
    $response->assertDownload('hirfaty-brochure.pdf');
});

/**
 * A rejected submission must not open the gate. Otherwise anyone could post
 * nonsense, get a validation error, and still walk off with the brochure.
 */
it('does not unlock the download when the form was rejected', function () {
    $this->post('/brochure', brochurePayload(['email' => 'not-an-email']));

    $this->get('/brochure/download')->assertForbidden();
});

it('does not unlock the download for a signed-in admin either', function () {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']));

    $this->get('/brochure/download')->assertForbidden();
});

// ── When the PDF was never uploaded ──────────────────────────────────────────

/**
 * The file is gitignored, so a freshly built environment can legitimately be
 * without it. Taking someone's details and then handing them a 404 is the one
 * outcome that must not happen.
 */
it('does not record a lead when the brochure is not on disk', function () {
    Storage::disk('local')->delete(BrochureLeadService::PATH);

    $response = $this->post('/brochure', brochurePayload());

    $response->assertSessionHasErrors('brochure', errorBag: 'brochure');

    expect(BrochureLead::count())->toBe(0);
});

it('404s the download when the brochure is not on disk', function () {
    $this->post('/brochure', brochurePayload());

    Storage::disk('local')->delete(BrochureLeadService::PATH);

    $this->get('/brochure/download')->assertNotFound();
});

// ── Throttling ───────────────────────────────────────────────────────────────

it('throttles repeated submissions', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/brochure', brochurePayload([
            'email' => "person{$i}@example.com",
        ]))->assertRedirect();
    }

    $this->post('/brochure', brochurePayload(['email' => 'person6@example.com']))
        ->assertStatus(429);

    expect(BrochureLead::count())->toBe(5);
});
