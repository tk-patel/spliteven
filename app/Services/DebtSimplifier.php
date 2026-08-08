<?php

declare(strict_types=1);

namespace App\Services;

class DebtSimplifier
{
    /**
     * Simplify debts to minimize number of transactions.
     *
     * Takes net balances where:
     *   Positive = this user is owed money (creditor)
     *   Negative = this user owes money (debtor)
     *
     * Returns minimal set of transactions to settle all debts.
     *
     * @param  array<int, float>  $balances  [user_id => net_balance]
     * @return array<array{from: int, to: int, amount: float}>
     */
    public static function simplify(array $balances): array
    {
        // Filter out zero/near-zero balances
        $balances = array_filter($balances, fn ($b) => abs($b) > 0.005);

        if (empty($balances)) {
            return [];
        }

        $creditors = []; // People who are owed money (positive balance)
        $debtors = [];   // People who owe money (negative balance)

        foreach ($balances as $userId => $balance) {
            if ($balance > 0) {
                $creditors[] = ['user_id' => $userId, 'amount' => round($balance, 2)];
            } elseif ($balance < 0) {
                $debtors[] = ['user_id' => $userId, 'amount' => round(abs($balance), 2)];
            }
        }

        // Sort descending by amount (settle largest first)
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $transactions = [];
        $i = 0; // debtor pointer
        $j = 0; // creditor pointer

        while ($i < count($debtors) && $j < count($creditors)) {
            $settleAmount = min($debtors[$i]['amount'], $creditors[$j]['amount']);

            if ($settleAmount > 0.005) {
                $transactions[] = [
                    'from' => $debtors[$i]['user_id'],   // this person pays
                    'to' => $creditors[$j]['user_id'],   // to this person
                    'amount' => round($settleAmount, 2),
                ];
            }

            $debtors[$i]['amount'] = round($debtors[$i]['amount'] - $settleAmount, 2);
            $creditors[$j]['amount'] = round($creditors[$j]['amount'] - $settleAmount, 2);

            if ($debtors[$i]['amount'] < 0.005) {
                $i++;
            }
            if ($creditors[$j]['amount'] < 0.005) {
                $j++;
            }
        }

        return $transactions;
    }
}
