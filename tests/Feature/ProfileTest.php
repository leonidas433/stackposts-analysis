<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/app/profile');

    $response->assertOk();
})->skip(fn () => ! databaseReady(), 'Database not available');

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post('/app/profile/update-profile', [
            'fullname' => 'Test User',
            'username' => $user->username,
            'email' => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 1);

    $user->refresh();

    $this->assertSame('Test User', $user->fullname);
})->skip(fn () => ! databaseReady(), 'Database not available');
