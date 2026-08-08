<?php

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;

test('authenticated user can create expense', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);

    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Lunch',
        'amount' => 50,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => null, 'amount' => null],
            ['user_id' => $bob->id, 'share_value' => null, 'amount' => null],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('expenses', [
        'description' => 'Lunch',
        'amount' => 50,
        'paid_by' => $alice->id,
    ]);
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $bob->id,
        'owed_amount' => 25,
    ]);
});

test('unauthenticated user cannot create expense', function () {
    $response = $this->post('/expenses', []);

    $response->assertRedirect('/login');
});

test('expense with shares split calculates correctly', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Dinner',
        'amount' => 100,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $alice->id,
        'split_type' => 'shares',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => 2, 'amount' => null],
            ['user_id' => $bob->id, 'share_value' => 1, 'amount' => null],
            ['user_id' => $charlie->id, 'share_value' => 1, 'amount' => null],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $alice->id,
        'owed_amount' => 50,
    ]);
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $bob->id,
        'owed_amount' => 25,
    ]);
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $charlie->id,
        'owed_amount' => 25,
    ]);
});

test('expense with percentage split calculates correctly', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Hotel',
        'amount' => 200,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $alice->id,
        'split_type' => 'percentage',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => 60, 'amount' => null],
            ['user_id' => $bob->id, 'share_value' => 40, 'amount' => null],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $alice->id,
        'owed_amount' => 120,
    ]);
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $bob->id,
        'owed_amount' => 80,
    ]);
});

test('expense with exact split validates sum', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Wrong sum -> rejected
    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Tickets',
        'amount' => 100,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $alice->id,
        'split_type' => 'exact',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => null, 'amount' => 60],
            ['user_id' => $bob->id, 'share_value' => null, 'amount' => 30],
        ],
    ]);

    $response->assertSessionHasErrors('split');

    // Correct sum -> created
    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Tickets',
        'amount' => 100,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $alice->id,
        'split_type' => 'exact',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => null, 'amount' => 60],
            ['user_id' => $bob->id, 'share_value' => null, 'amount' => 40],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $alice->id,
        'owed_amount' => 60,
    ]);
    $this->assertDatabaseHas('expense_participants', [
        'user_id' => $bob->id,
        'owed_amount' => 40,
    ]);
});

test('payer must be a participant', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $response = $this->actingAs($alice)->post('/expenses', [
        'description' => 'Lunch',
        'amount' => 50,
        'expense_date' => '2026-08-06',
        'group_id' => null,
        'paid_by' => $charlie->id,
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $alice->id, 'share_value' => null, 'amount' => null],
            ['user_id' => $bob->id, 'share_value' => null, 'amount' => null],
        ],
    ]);

    $response->assertSessionHasErrors('paid_by');
});

test('expense in group requires membership', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id]);

    $response = $this->actingAs($charlie)->post('/expenses', [
        'description' => 'Lunch',
        'amount' => 50,
        'expense_date' => '2026-08-06',
        'group_id' => $group->id,
        'paid_by' => $charlie->id,
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $charlie->id, 'share_value' => null, 'amount' => null],
            ['user_id' => $alice->id, 'share_value' => null, 'amount' => null],
        ],
    ]);

    $response->assertForbidden();
});

test('non-participant cannot view expense', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Lunch',
        'amount' => 50,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $alice->id,
        'owed_amount' => 25,
    ]);
    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $bob->id,
        'owed_amount' => 25,
    ]);

    $response = $this->actingAs($charlie)->get("/expenses/{$expense->id}");

    $response->assertForbidden();
});

test('only creator can delete expense', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Lunch',
        'amount' => 50,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    $response = $this->actingAs($bob)->delete("/expenses/{$expense->id}");
    $response->assertForbidden();

    $response = $this->actingAs($alice)->delete("/expenses/{$expense->id}");
    $response->assertRedirect();
    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});
