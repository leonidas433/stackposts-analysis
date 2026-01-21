<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('recovery password screen can be rendered', function () {
    $response = $this->get('/auth/recovery-password');

    $response->assertStatus(200);
})->skip(fn () => ! databaseReady(), 'Database not available');

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();
    $token = 'token-123';

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make('different-token'),
        'created_at' => now(),
    ]);

    $response = $this->post('/auth/do_recovery_password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 0);
})->skip(fn () => ! databaseReady(), 'Database not available');
