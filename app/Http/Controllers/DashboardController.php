<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BalanceCalculator;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with all balances and overall summary.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $userId = $user->id;

        $rawBalances = BalanceCalculator::getAllBalancesForUser($userId);

        // Enrich with user data
        $userIds = array_keys($rawBalances);
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'username'])->keyBy('id');

        $balances = [];
        $totalOwed = 0;   // What you owe others (positive balances)
        $totalOwing = 0;  // What others owe you (negative balances)

        foreach ($rawBalances as $otherUserId => $amount) {
            $balances[] = [
                'user' => $users[$otherUserId] ?? null,
                'amount' => $amount,
            ];

            if ($amount > 0) {
                $totalOwed += $amount;  // You owe this person
            } else {
                $totalOwing += abs($amount);  // This person owes you
            }
        }

        // Sort: people you owe first (desc), then people who owe you (desc)
        usort($balances, function ($a, $b) {
            if ($a['amount'] > 0 && $b['amount'] <= 0) {
                return -1;
            }
            if ($a['amount'] <= 0 && $b['amount'] > 0) {
                return 1;
            }

            return abs($b['amount']) <=> abs($a['amount']);
        });

        return inertia('Dashboard', [
            'balances' => $balances,
            'totalOwed' => round($totalOwed, 2),
            'totalOwing' => round($totalOwing, 2),
            'netBalance' => round($totalOwing - $totalOwed, 2),
        ]);
    }
}
