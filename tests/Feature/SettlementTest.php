<?php

use App\Models\Friendship;
use App\Models\Settlement;
use App\Models\User;

test('user can record a settlement', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);

    $response = $this->actingAs($alice)->post(route('settlements.store'), [
        'payee_id' => $bob->id,
        'amount' => 50,
        'group_id' => null,
        'note' => 'Dinner payback',
        'settled_at' => '2026-08-06',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('settlements', [
        'payer_id' => $alice->id,
        'payee_id' => $bob->id,
        'amount' => 50,
        'note' => 'Dinner payback',
    ]);
});

test('user cannot settle with themselves', function () {
    $alice = User::factory()->create();

    $response = $this->actingAs($alice)->post(route('settlements.store'), [
        'payee_id' => $alice->id,
        'amount' => 50,
        'group_id' => null,
        'note' => null,
        'settled_at' => '2026-08-06',
    ]);

    $response->assertSessionHasErrors('payee_id');
    $this->assertDatabaseCount('settlements', 0);
});

test('settlement requires a positive amount', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $response = $this->actingAs($alice)->post(route('settlements.store'), [
        'payee_id' => $bob->id,
        'amount' => 0,
        'group_id' => null,
        'note' => null,
        'settled_at' => '2026-08-06',
    ]);

    $response->assertSessionHasErrors('amount');
});

test('settlements index shows user settlements', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Settlement::create([
        'payer_id' => $alice->id,
        'payee_id' => $bob->id,
        'amount' => 25,
        'settled_at' => now(),
    ]);

    $response = $this->actingAs($alice)->get(route('settlements.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settlements/Index')
            ->has('settlements.data', 1),
        );
});
