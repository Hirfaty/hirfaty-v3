<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
});

test('admin sends a password reset link to a buyer', function () {
    Notification::fake();

    $buyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.send-reset-link', $buyer))
        ->assertRedirect();

    Notification::assertSentTo($buyer, ResetPasswordNotification::class);
});

test('admin sends a password reset link to a seller', function () {
    Notification::fake();

    $seller = User::factory()->create(['role' => 'seller']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.send-reset-link', $seller))
        ->assertRedirect();

    Notification::assertSentTo($seller, ResetPasswordNotification::class);
});

test('admin cannot send a reset link to another super admin', function () {
    Notification::fake();

    $otherAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.send-reset-link', $otherAdmin))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('a non-admin cannot trigger a password reset from this endpoint', function () {
    $buyer = User::factory()->create(['role' => 'buyer']);
    $otherBuyer = User::factory()->create(['role' => 'buyer']);

    $this->actingAs($buyer)
        ->post(route('admin.users.send-reset-link', $otherBuyer))
        ->assertForbidden();
});
