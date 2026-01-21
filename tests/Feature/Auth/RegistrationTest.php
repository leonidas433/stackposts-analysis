<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/auth/signup');

    $response->assertStatus(200);
})->skip(fn () => ! databaseReady(), 'Database not available');

test('new users can register', function () {
    $response = $this->post('/auth/do_signup', [
        'fullname' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'timezone' => 'UTC',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 1);
    $this->assertAuthenticated();
})->skip(fn () => ! databaseReady(), 'Database not available');
