<?php

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Services\BalanceCalculator;

test('calculates simple pairwise balance', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice pays $100, split equally with Bob
    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 100,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $alice->id,
        'owed_amount' => 50,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $bob->id,
        'owed_amount' => 50,
    ]);

    // Bob owes Alice $50
    $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
    expect($balance)->toBe(50.00); // positive = Bob owes Alice

    // Alice's perspective: Alice is owed $50 by Bob
    $balance2 = BalanceCalculator::getNetBalance($alice->id, $bob->id);
    expect($balance2)->toBe(-50.00); // negative = Alice is owed by Bob
});

test('settlements reduce balance', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 100,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $alice->id,
        'owed_amount' => 50,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $bob->id,
        'owed_amount' => 50,
    ]);

    // Bob pays Alice $30
    Settlement::create([
        'payer_id' => $bob->id,
        'payee_id' => $alice->id,
        'amount' => 30,
        'settled_at' => now(),
    ]);

    // Bob now owes Alice $20
    $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
    expect($balance)->toBe(20.00);
});

test('full settlement zeroes balance', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $expense = Expense::create([
        'description' => 'Dinner',
        'amount' => 100,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $alice->id,
        'owed_amount' => 50,
    ]);

    ExpenseParticipant::create([
        'expense_id' => $expense->id,
        'user_id' => $bob->id,
        'owed_amount' => 50,
    ]);

    Settlement::create([
        'payer_id' => $bob->id,
        'payee_id' => $alice->id,
        'amount' => 50,
        'settled_at' => now(),
    ]);

    $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
    expect($balance)->toBe(0.00);
});

test('group balances are correct', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $charlie = User::factory()->create();

    $group = Group::create(['name' => 'Trip', 'created_by' => $alice->id]);
    $group->members()->attach([$alice->id, $bob->id, $charlie->id]);

    // Alice pays $90, split equally 3 ways ($30 each)
    $expense = Expense::create([
        'group_id' => $group->id,
        'description' => 'Hotel',
        'amount' => 90,
        'paid_by' => $alice->id,
        'split_type' => 'equal',
        'expense_date' => now(),
        'created_by' => $alice->id,
    ]);

    foreach ([$alice->id, $bob->id, $charlie->id] as $userId) {
        ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'user_id' => $userId,
            'owed_amount' => 30,
        ]);
    }

    $balances = BalanceCalculator::getGroupBalances($group->id);

    // Alice paid 90, owed 30 → net +60 (others owe her)
    expect($balances[$alice->id])->toBe(60.00);
    // Bob owed 30 → net -30
    expect($balances[$bob->id])->toBe(-30.00);
    // Charlie owed 30 → net -30
    expect($balances[$charlie->id])->toBe(-30.00);
});

test('get all balances for user aggregates correctly', function () {
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

    // Alice is owed $25 by Bob → balance for Bob is -25 (they owe Alice)
    $balances = BalanceCalculator::getAllBalancesForUser($alice->id);
    expect($balances[$bob->id])->toBe(-25.00);

    // Bob owes Alice $25 → balance for Alice is +25
    $balancesBob = BalanceCalculator::getAllBalancesForUser($bob->id);
    expect($balancesBob[$alice->id])->toBe(25.00);
});
