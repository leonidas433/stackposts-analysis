<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post('/app/profile/change-password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 1);

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
})->skip(fn () => ! databaseReady(), 'Database not available');

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post('/app/profile/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 0);
})->skip(fn () => ! databaseReady(), 'Database not available');
