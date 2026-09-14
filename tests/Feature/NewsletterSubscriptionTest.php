<?php

use App\Models\NewsletterSubscriber;

/**
 * The footer's mailing-list signup.
 *
 * The interesting behaviour is all in what happens to an address that is *not*
 * new — a duplicate, a different capitalisation, one with whitespace around it —
 * because that is what a form sitting on every page of the site will actually
 * be sent.
 */
it('subscribes a visitor who is not signed in', function () {
    $response = $this->post('/newsletter', ['email' => 'amina@example.com']);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(NewsletterSubscriber::where('email', 'amina@example.com')->exists())->toBeTrue();
});

it('stores the address lower-cased and trimmed', function () {
    $this->post('/newsletter', ['email' => '  Amina@Example.COM  ']);

    expect(NewsletterSubscriber::pluck('email')->all())->toBe(['amina@example.com']);
});

it('does not create a second row for the same mailbox', function () {
    $this->post('/newsletter', ['email' => 'amina@example.com']);
    $response = $this->post('/newsletter', ['email' => 'AMINA@example.com']);

    // Still a success from the visitor's side: saying otherwise would leak
    // whether a given person is on the list.
    $response->assertSessionHas('success');
    $response->assertSessionHasNoErrors();

    expect(NewsletterSubscriber::count())->toBe(1);
});

it('records the locale the visitor was reading in', function () {
    $this->withSession(['locale' => 'ar'])->post('/newsletter', ['email' => 'amina@example.com']);

    expect(NewsletterSubscriber::first()->locale)->toBe('ar');
});

it('refreshes the stored locale when someone subscribes again in another language', function () {
    $this->withSession(['locale' => 'fr'])->post('/newsletter', ['email' => 'amina@example.com']);
    $this->withSession(['locale' => 'ar'])->post('/newsletter', ['email' => 'amina@example.com']);

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and(NewsletterSubscriber::first()->locale)->toBe('ar');
});

it('rejects a malformed address without storing anything', function () {
    $response = $this->post('/newsletter', ['email' => 'not-an-email']);

    $response->assertSessionHasErrors('email', errorBag: 'newsletter');

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('rejects a missing address', function () {
    $response = $this->post('/newsletter', ['email' => '']);

    $response->assertSessionHasErrors('email', errorBag: 'newsletter');

    expect(NewsletterSubscriber::count())->toBe(0);
});

/**
 * The errors have to stay out of the default bag. This form is in the footer of
 * every page, so a rejection landing in `default` would light up the `email`
 * field of whatever other form shares the screen.
 */
it('keeps its validation errors out of the default bag', function () {
    $response = $this->post('/newsletter', ['email' => 'not-an-email']);

    $response->assertSessionHasErrorsIn('newsletter', ['email']);
    $response->assertSessionDoesntHaveErrors('email');
});

it('throttles repeated submissions', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/newsletter', ['email' => "person{$i}@example.com"])
            ->assertRedirect();
    }

    $this->post('/newsletter', ['email' => 'person6@example.com'])
        ->assertStatus(429);

    expect(NewsletterSubscriber::count())->toBe(5);
});
