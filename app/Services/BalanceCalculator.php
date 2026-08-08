<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseParticipant;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

class BalanceCalculator
{
    /**
     * Calculate the net balance between two users.
     *
     * RETURNS:
     *   Positive number = $userId OWES $otherUserId that amount
     *   Negative number = $otherUserId OWES $userId that amount
     *   Zero = settled
     *
     * @param  int|null  $groupId  If provided, only considers expenses/settlements in that group
     */
    public static function getNetBalance(int $userId, int $otherUserId, ?int $groupId = null): float
    {
        // Step 1: What $userId owes from expenses paid by $otherUserId
        $userOwes = ExpenseParticipant::query()
            ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
            ->where('expense_participants.user_id', $userId)
            ->where('expenses.paid_by', $otherUserId)
            ->when($groupId !== null, fn ($q) => $q->where('expenses.group_id', $groupId))
            ->when($groupId === null, fn ($q) => $q) // all expenses
            ->sum('expense_participants.owed_amount');

        // Step 2: What $otherUserId owes from expenses paid by $userId
        $otherOwes = ExpenseParticipant::query()
            ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
            ->where('expense_participants.user_id', $otherUserId)
            ->where('expenses.paid_by', $userId)
            ->when($groupId !== null, fn ($q) => $q->where('expenses.group_id', $groupId))
            ->sum('expense_participants.owed_amount');

        // Step 3: Settlements from $userId to $otherUserId
        $settledByUser = Settlement::query()
            ->where('payer_id', $userId)
            ->where('payee_id', $otherUserId)
            ->when($groupId !== null, fn ($q) => $q->where('group_id', $groupId))
            ->sum('amount');

        // Step 4: Settlements from $otherUserId to $userId
        $settledByOther = Settlement::query()
            ->where('payer_id', $otherUserId)
            ->where('payee_id', $userId)
            ->when($groupId !== null, fn ($q) => $q->where('group_id', $groupId))
            ->sum('amount');

        // Net balance: positive = $userId owes $otherUserId
        $net = ((float) $userOwes - (float) $settledByUser)
             - ((float) $otherOwes - (float) $settledByOther);

        return round($net, 2);
    }

    /**
     * Get all balances for a user with every person they share expenses with.
     *
     * @return array<int, float> [other_user_id => net_balance]
     *                           Positive = auth user owes them
     *                           Negative = they owe auth user
     */
    public static function getAllBalancesForUser(int $userId): array
    {
        // Find all users connected via expenses or settlements
        $relatedUserIds = collect();

        // Users in expenses paid by $userId (they owe the user)
        $relatedUserIds = $relatedUserIds->merge(
            ExpenseParticipant::query()
                ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
                ->where('expenses.paid_by', $userId)
                ->where('expense_participants.user_id', '!=', $userId)
                ->distinct()
                ->pluck('expense_participants.user_id')
        );

        // Users who paid for expenses that include $userId
        $relatedUserIds = $relatedUserIds->merge(
            ExpenseParticipant::query()
                ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
                ->where('expense_participants.user_id', $userId)
                ->where('expenses.paid_by', '!=', $userId)
                ->distinct()
                ->pluck('expenses.paid_by')
        );

        // Users in settlements with $userId
        $relatedUserIds = $relatedUserIds->merge(
            Settlement::query()
                ->where(function ($q) use ($userId) {
                    $q->where('payer_id', $userId)
                        ->orWhere('payee_id', $userId);
                })
                ->get()
                ->map(fn ($s) => $s->payer_id === $userId ? $s->payee_id : $s->payer_id)
        );

        $relatedUserIds = $relatedUserIds->unique()->values();

        $balances = [];
        foreach ($relatedUserIds as $otherUserId) {
            $balance = self::getNetBalance($userId, (int) $otherUserId);
            if (abs($balance) > 0.005) {
                $balances[(int) $otherUserId] = $balance;
            }
        }

        return $balances;
    }

    /**
     * Get balances within a specific group.
     *
     * @return array<int, float> [user_id => net_balance_in_group]
     *                           Positive = this user is owed money
     *                           Negative = this user owes money
     */
    public static function getGroupBalances(int $groupId): array
    {
        $balances = [];

        // Get all participants in this group's expenses
        $userIds = DB::table('expense_participants')
            ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
            ->where('expenses.group_id', $groupId)
            ->distinct()
            ->pluck('expense_participants.user_id')
            ->merge(
                DB::table('expenses')
                    ->where('group_id', $groupId)
                    ->distinct()
                    ->pluck('paid_by')
            )
            ->merge(
                DB::table('settlements')
                    ->where('group_id', $groupId)
                    ->pluck('payer_id')
            )
            ->merge(
                DB::table('settlements')
                    ->where('group_id', $groupId)
                    ->pluck('payee_id')
            )
            ->unique()
            ->values();

        foreach ($userIds as $uid) {
            // For each user, their net in the group:
            // They PAID this much total
            $totalPaid = (float) DB::table('expenses')
                ->where('group_id', $groupId)
                ->where('paid_by', $uid)
                ->sum('amount');

            // They OWE this much total (their share of all group expenses)
            $totalOwed = (float) DB::table('expense_participants')
                ->join('expenses', 'expense_participants.expense_id', '=', 'expenses.id')
                ->where('expenses.group_id', $groupId)
                ->where('expense_participants.user_id', $uid)
                ->sum('expense_participants.owed_amount');

            // They SENT this much in settlements
            $totalSettledOut = (float) DB::table('settlements')
                ->where('group_id', $groupId)
                ->where('payer_id', $uid)
                ->sum('amount');

            // They RECEIVED this much in settlements
            $totalSettledIn = (float) DB::table('settlements')
                ->where('group_id', $groupId)
                ->where('payee_id', $uid)
                ->sum('amount');

            // Net: positive = owed money, negative = owes money
            $net = ($totalPaid - $totalOwed) - $totalSettledIn + $totalSettledOut;
            $net = round($net, 2);

            if (abs($net) > 0.005) {
                $balances[(int) $uid] = $net;
            }
        }

        return $balances;
    }
}
