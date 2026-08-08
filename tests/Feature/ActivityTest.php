<?php

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Settlement;
use App\Models\User;

test('activity feed shows expenses for payer', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 50,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $alice->id, 'owed_amount' => 25]);
    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $bob->id, 'owed_amount' => 25]);

    $response = $this->actingAs($alice)->get(route('activity.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->has('activities', 1)
            ->where('activities.0.type', 'expense'),
        );
});

test('activity feed shows expenses for participant', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 50,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $alice->id, 'owed_amount' => 25]);
    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $bob->id, 'owed_amount' => 25]);

    // Bob is a participant, sees the expense too
    $response = $this->actingAs($bob)->get(route('activity.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->has('activities', 1),
        );
});

test('activity feed shows settlements', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Settlement::create([
        'payer_id' => $bob->id,
        'payee_id' => $alice->id,
        'amount' => 25,
        'settled_at' => now(),
    ]);

    $response = $this->actingAs($alice)->get(route('activity.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->has('activities', 1)
            ->where('activities.0.type', 'settlement'),
        );
});

test('activity feed is empty for unrelated user', function () {
    $alice = User::factory()->create();
    $stranger = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 50,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $alice->id, 'owed_amount' => 50]);

    $response = $this->actingAs($stranger)->get(route('activity.index'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->where('activities', []),
        );
});
