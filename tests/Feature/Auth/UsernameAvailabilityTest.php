<?php

use App\Models\User;

test('check-username returns available for a new username', function () {
    $response = $this->post(route('check-username'), [
        'username' => 'fresh_user',
    ]);

    $response
        ->assertOk()
        ->assertExactJson(['available' => true]);
});

test('check-username returns unavailable for an existing username', function () {
    User::factory()->create(['username' => 'taken_user']);

    $response = $this->post(route('check-username'), [
        'username' => 'taken_user',
    ]);

    $response
        ->assertOk()
        ->assertExactJson(['available' => false]);
});

test('check-username is case insensitive', function () {
    User::factory()->create(['username' => 'taken_user']);

    $response = $this->post(route('check-username'), [
        'username' => 'TAKEN_USER',
    ]);

    $response
        ->assertOk()
        ->assertExactJson(['available' => false]);
});

test('check-username validates the username field', function () {
    $response = $this->post(route('check-username'), [
        'username' => '',
    ]);

    $response->assertSessionHasErrors('username');
});
