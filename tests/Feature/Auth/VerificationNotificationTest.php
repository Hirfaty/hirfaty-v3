<?php

use App\Models\User;
use App\Notifications\BuyerVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('root'));

    // User::sendEmailVerificationNotification() sends the role-specific
    // mail, and a factory user is a buyer.
    Notification::assertSentTo($user, BuyerVerifyEmail::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    // Same RedirectAsIntended('email-verification') fallback as the prompt
    // controller — not role-based, always config('fortify.home').
    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(config('fortify.home'));

    Notification::assertNothingSent();
});
