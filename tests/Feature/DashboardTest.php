<?php

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Friendship;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('dashboard shows settled up state when no balances', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('balances', [])
            ->where('totalOwed', 0)
            ->where('totalOwing', 0)
            ->where('netBalance', 0),
        );
});

test('dashboard shows correct balances after expense', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Friendship::create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => 'accepted',
    ]);

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 100,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $alice->id, 'owed_amount' => 50]);
    ExpenseParticipant::create(['expense_id' => $expense->id, 'user_id' => $bob->id, 'owed_amount' => 50]);

    // Alice's dashboard: Bob owes her $50
    $response = $this->actingAs($alice)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('balances', 1)
            ->where('balances.0.user.id', $bob->id)
            ->where('balances.0.amount', -50)
            ->where('totalOwing', 50)
            ->where('totalOwed', 0)
            ->where('netBalance', 50),
        );
});
