<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/auth/login');

    $response->assertStatus(200);
})->skip(fn () => ! databaseReady(), 'Database not available');

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/auth/do_login', [
        'username' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertOk();
    $response->assertJsonPath('status', 1);
})->skip(fn () => ! databaseReady(), 'Database not available');

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post('/auth/do_login', [
        'username' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertOk();
    $response->assertJsonPath('status', 0);
})->skip(fn () => ! databaseReady(), 'Database not available');

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/auth/logout');

    $this->assertGuest();
    $response->assertRedirect('auth/login');
})->skip(fn () => ! databaseReady(), 'Database not available');
