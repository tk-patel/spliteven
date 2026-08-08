<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class SplitCalculator
{
    /**
     * Split an amount equally among participants.
     * Handles rounding: last person absorbs the remainder.
     *
     * @param  float  $totalAmount  The total expense amount
     * @param  array<int>  $participantIds  Array of user IDs
     * @return array<int, string> [user_id => owed_amount as string]
     */
    public static function equal(float $totalAmount, array $participantIds): array
    {
        $count = count($participantIds);
        if ($count === 0) {
            throw new InvalidArgumentException('At least one participant required.');
        }

        $perPerson = bcdiv((string) $totalAmount, (string) $count, 2);
        $allocated = '0.00';
        $splits = [];

        foreach ($participantIds as $index => $userId) {
            if ($index === $count - 1) {
                // Last person gets the remainder to handle rounding
                $splits[$userId] = bcsub((string) $totalAmount, $allocated, 2);
            } else {
                $splits[$userId] = $perPerson;
                $allocated = bcadd($allocated, $perPerson, 2);
            }
        }

        return $splits;
    }

    /**
     * Split by share counts.
     * Example: Alice=2 shares, Bob=1 share, total=3 shares.
     * Alice owes (2/3)*amount, Bob owes (1/3)*amount.
     *
     * @param  array<int, float>  $participantShares  [user_id => share_count]
     * @return array<int, string> [user_id => owed_amount]
     */
    public static function byShares(float $totalAmount, array $participantShares): array
    {
        if (empty($participantShares)) {
            throw new InvalidArgumentException('At least one participant required.');
        }

        $totalShares = array_sum($participantShares);
        if ($totalShares <= 0) {
            throw new InvalidArgumentException('Total shares must be greater than zero.');
        }

        $allocated = '0.00';
        $splits = [];
        $participants = array_keys($participantShares);
        $lastIndex = count($participants) - 1;

        foreach ($participants as $index => $userId) {
            if ($index === $lastIndex) {
                $splits[$userId] = bcsub((string) $totalAmount, $allocated, 2);
            } else {
                $fraction = bcdiv((string) $participantShares[$userId], (string) $totalShares, 10);
                $amount = bcmul((string) $totalAmount, $fraction, 2);
                $splits[$userId] = $amount;
                $allocated = bcadd($allocated, $amount, 2);
            }
        }

        return $splits;
    }

    /**
     * Split by percentage.
     * Percentages MUST sum to 100.
     *
     * @param  array<int, float>  $participantPercentages  [user_id => percentage]
     * @return array<int, string> [user_id => owed_amount]
     */
    public static function byPercentage(float $totalAmount, array $participantPercentages): array
    {
        if (empty($participantPercentages)) {
            throw new InvalidArgumentException('At least one participant required.');
        }

        $totalPercent = (float) array_sum($participantPercentages);
        if (abs($totalPercent - 100.0) > 0.01) {
            throw new InvalidArgumentException(
                "Percentages must sum to 100. Got: {$totalPercent}"
            );
        }

        $allocated = '0.00';
        $splits = [];
        $participants = array_keys($participantPercentages);
        $lastIndex = count($participants) - 1;

        foreach ($participants as $index => $userId) {
            if ($index === $lastIndex) {
                $splits[$userId] = bcsub((string) $totalAmount, $allocated, 2);
            } else {
                $fraction = bcdiv((string) $participantPercentages[$userId], '100', 10);
                $amount = bcmul((string) $totalAmount, $fraction, 2);
                $splits[$userId] = $amount;
                $allocated = bcadd($allocated, $amount, 2);
            }
        }

        return $splits;
    }

    /**
     * Split by exact amounts.
     * Exact amounts MUST sum to the total.
     *
     * @param  array<int, float>  $participantAmounts  [user_id => exact_amount]
     * @return array<int, string> [user_id => owed_amount]
     */
    public static function byExact(float $totalAmount, array $participantAmounts): array
    {
        if (empty($participantAmounts)) {
            throw new InvalidArgumentException('At least one participant required.');
        }

        $totalSpecified = '0.00';
        $splits = [];

        foreach ($participantAmounts as $userId => $amount) {
            $amountStr = number_format((float) $amount, 2, '.', '');
            $splits[$userId] = $amountStr;
            $totalSpecified = bcadd($totalSpecified, $amountStr, 2);
        }

        if (bccomp($totalSpecified, number_format($totalAmount, 2, '.', ''), 2) !== 0) {
            throw new InvalidArgumentException(
                "Exact amounts must sum to {$totalAmount}. Got: {$totalSpecified}"
            );
        }

        return $splits;
    }
}
