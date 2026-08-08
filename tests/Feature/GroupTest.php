<?php

use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;

test('user can create a group with friends', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    foreach ([$bob, $charlie] as $friend) {
        Friendship::create([
            'requester_id' => $alice->id,
            'addressee_id' => $friend->id,
            'status' => 'accepted',
        ]);
    }

    $response = $this->actingAs($alice)->post(route('groups.store'), [
        'name' => 'Weekend Trip',
        'member_ids' => [$bob->id, $charlie->id],
    ]);

    $group = Group::where('name', 'Weekend Trip')->first();

    $response->assertRedirect(route('groups.show', $group));
    $this->assertDatabaseHas('groups', [
        'name' => 'Weekend Trip',
        'created_by' => $alice->id,
    ]);

    $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $alice->id]);
    $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $bob->id]);
    $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $charlie->id]);
});

test('group creation requires at least one member', function () {
    $alice = User::factory()->create();

    $response = $this->actingAs($alice)->post(route('groups.store'), [
        'name' => 'Solo Group',
        'member_ids' => [],
    ]);

    $response->assertSessionHasErrors('member_ids');
});

test('group creation rejects non-circle members', function () {
    $alice = User::factory()->create();
    $stranger = User::factory()->create();

    $response = $this->actingAs($alice)->post(route('groups.store'), [
        'name' => 'Suspicious Group',
        'member_ids' => [$stranger->id],
    ]);

    $response->assertSessionHasErrors('member_ids');
});

test('group index lists user groups', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id]);

    $response = $this->actingAs($alice)->get(route('groups.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Groups/Index')
            ->has('groups', 1)
            ->where('groups.0.name', 'Trip'),
        );
});

test('non-member cannot view group', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $group = Group::create(['name' => 'Secret', 'created_by' => $alice->id]);
    $group->members()->attach($alice->id);

    $response = $this->actingAs($bob)->get(route('groups.show', $group));

    $response->assertForbidden();
});

test('creator can add member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);
    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $charlie->id,
        'status' => 'accepted',
    ]);

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id]);

    $response = $this->actingAs($alice)->post(route('groups.members.add', $group), [
        'user_id' => $charlie->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $charlie->id]);
});

test('non-creator cannot add member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id]);

    $response = $this->actingAs($bob)->post(route('groups.members.add', $group), [
        'user_id' => $charlie->id,
    ]);

    $response->assertForbidden();
});

test('creator can remove member but not themselves', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id]);

    // Remove Bob
    $response = $this->actingAs($alice)->delete(route('groups.members.remove', [$group, $bob]));
    $response->assertRedirect();
    $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $bob->id]);

    // Cannot remove self (creator)
    $response = $this->actingAs($alice)->delete(route('groups.members.remove', [$group, $alice]));
    $response->assertSessionHasErrors();
    $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $alice->id]);
});
