<?php

use App\Services\DebtSimplifier;

test('simplifies basic two person debt', function () {
    // User 1 is owed 50, User 2 owes 50
    $result = DebtSimplifier::simplify([1 => 50, 2 => -50]);

    expect($result)->toHaveCount(1);
    expect($result[0]['from'])->toBe(2);
    expect($result[0]['to'])->toBe(1);
    expect($result[0]['amount'])->toBe(50.0);
});

test('simplifies three person debt', function () {
    // User 1 is owed 100, User 2 owes 60, User 3 owes 40
    $result = DebtSimplifier::simplify([1 => 100, 2 => -60, 3 => -40]);

    expect($result)->toHaveCount(2);

    // Check total amounts flow correctly
    $totalToUser1 = collect($result)->where('to', 1)->sum('amount');
    expect($totalToUser1)->toBe(100.0);
});

test('handles empty balances', function () {
    $result = DebtSimplifier::simplify([]);

    expect($result)->toBeEmpty();
});

test('handles already settled', function () {
    $result = DebtSimplifier::simplify([1 => 0, 2 => 0]);

    expect($result)->toBeEmpty();
});

test('minimizes transactions', function () {
    // 4 people: A owes 30, B owes 20, C is owed 40, D is owed 10
    $result = DebtSimplifier::simplify([
        1 => -30, 2 => -20, 3 => 40, 4 => 10,
    ]);

    // Should be 2-3 transactions, not 6 (all pairs)
    expect(count($result))->toBeLessThan(4);

    // Verify net balances are preserved
    $nets = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    foreach ($result as $txn) {
        $nets[$txn['from']] -= $txn['amount'];
        $nets[$txn['to']] += $txn['amount'];
    }

    expect(round($nets[1], 2))->toBe(-30.0);
    expect(round($nets[2], 2))->toBe(-20.0);
    expect(round($nets[3], 2))->toBe(40.0);
    expect(round($nets[4], 2))->toBe(10.0);
});
