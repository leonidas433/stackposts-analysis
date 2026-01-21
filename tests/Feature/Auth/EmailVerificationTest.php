<?php

test('activation screen can be rendered', function () {
    $response = $this->get('/auth/activation');

    $response->assertStatus(200);
})->skip(fn () => ! databaseReady(), 'Database not available');
