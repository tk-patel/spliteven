<?php

use App\Services\SplitCalculator;
use InvalidArgumentException;

test('equal split divides evenly', function () {
    $result = SplitCalculator::equal(100.00, [1, 2]);

    expect($result)->toBe(['1' => '50.00', '2' => '50.00']);
});

test('equal split handles rounding', function () {
    $result = SplitCalculator::equal(100.00, [1, 2, 3]);

    // 100/3 = 33.33, last person gets 33.34
    expect($result)->toBe(['1' => '33.33', '2' => '33.33', '3' => '33.34']);

    // Total must equal original amount
    $total = bcadd(bcadd($result[1], $result[2], 2), $result[3], 2);
    expect($total)->toBe('100.00');
});

test('equal split rejects empty participants', function () {
    expect(fn () => SplitCalculator::equal(100.00, []))
        ->toThrow(InvalidArgumentException::class);
});

test('shares split calculates correctly', function () {
    $result = SplitCalculator::byShares(100.00, [1 => 2, 2 => 1, 3 => 1]);

    expect($result)->toBe(['1' => '50.00', '2' => '25.00', '3' => '25.00']);
});

test('shares split handles rounding', function () {
    // 100 split 1:1:1 = same as equal
    $result = SplitCalculator::byShares(100.00, [1 => 1, 2 => 1, 3 => 1]);

    $total = bcadd(bcadd($result[1], $result[2], 2), $result[3], 2);
    expect($total)->toBe('100.00');
});

test('percentage split calculates correctly', function () {
    $result = SplitCalculator::byPercentage(200.00, [1 => 50, 2 => 30, 3 => 20]);

    expect($result)->toBe(['1' => '100.00', '2' => '60.00', '3' => '40.00']);
});

test('percentage split rejects non-100 total', function () {
    expect(fn () => SplitCalculator::byPercentage(100.00, [1 => 50, 2 => 30]))
        ->toThrow(InvalidArgumentException::class);
});

test('exact split validates sum', function () {
    $result = SplitCalculator::byExact(100.00, [1 => 60, 2 => 40]);

    expect($result)->toBe(['1' => '60.00', '2' => '40.00']);
});

test('exact split rejects wrong sum', function () {
    expect(fn () => SplitCalculator::byExact(100.00, [1 => 60, 2 => 30]))
        ->toThrow(InvalidArgumentException::class);
});
