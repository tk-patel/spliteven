<?php

use App\Models\Friendship;
use App\Models\User;

test('user can send friend request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $response = $this->actingAs($alice)->post("/circle/invite/{$bob->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('friendships', [
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'pending',
    ]);
});

test('user can accept friend request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $friendship = Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($bob)->post("/circle/accept/{$friendship->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('friendships', [
        'id' => $friendship->id,
        'status' => 'accepted',
    ]);
});

test('user cannot accept others request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $friendship = Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($charlie)->post("/circle/accept/{$friendship->id}");

    $response->assertForbidden();
});

test('cannot send duplicate friend request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($alice)->post("/circle/invite/{$bob->id}");

    $response->assertSessionHasErrors();
});

test('cannot invite yourself', function () {
    $alice = User::factory()->create();

    $response = $this->actingAs($alice)->post("/circle/invite/{$alice->id}");

    $response->assertSessionHasErrors();
});

test('user can reject friend request', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $friendship = Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($bob)->post("/circle/reject/{$friendship->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('friendships', [
        'id' => $friendship->id,
        'status' => 'rejected',
    ]);
});

test('user can remove friend', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $friendship = Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);

    $response = $this->actingAs($alice)->delete("/circle/{$friendship->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
});

test('circle index shows friends and pending requests', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);

    $charlie = User::factory()->create();

    Friendship::create([
        'requester_id' => $charlie->id,
        'addressee_id' => $alice->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($alice)->get(route('circle.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Circle/Index')
            ->has('friends', 1)
            ->has('pendingReceived', 1)
            ->has('pendingSent', 0),
        );
});

test('circle search finds users by username', function () {
    $alice = User::factory()->create(['username' => 'alice_01']);
    User::factory()->create(['username' => 'bob_02']);

    $response = $this->actingAs($alice)->postJson(route('circle.search'), [
        'query' => 'bob',
    ]);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.username', 'bob_02');
});

test('circle search excludes current user', function () {
    $alice = User::factory()->create(['username' => 'alice_01']);

    $response = $this->actingAs($alice)->postJson(route('circle.search'), [
        'query' => 'alice',
    ]);

    $response
        ->assertOk()
        ->assertJsonCount(0, 'results');
});
