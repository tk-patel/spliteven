<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register with a username', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'username' => 'test_user',
        'email' => 'test@example.com',
    ]);
});

test('uppercase usernames are rejected', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'TestUser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('registration requires a username', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('username must be at least 3 characters', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'ab',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
});

test('username must not exceed 30 characters', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => str_repeat('a', 31),
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
});

test('username only allows lowercase letters, numbers and underscores', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'Invalid-Name!',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
});

test('duplicate username is rejected', function () {
    User::factory()->create(['username' => 'taken_user']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'taken_user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('login still works with email', function () {
    $user = User::factory()->create([
        'username' => 'login_user',
        'email' => 'login@example.com',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});
