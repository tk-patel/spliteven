<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Group;
use App\Services\SplitCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Show the add expense form.
     */
    public function create(Request $request): Response
    {
        $user = auth()->user();
        $friends = collect($user->friends())->map(fn ($f) => [
            'id' => $f['id'],
            'name' => $f['name'],
            'username' => $f['username'],
        ])->values();

        $groups = $user->groups()->with('members:id,name,username')->get();

        return inertia('Expenses/Create', [
            'friends' => $friends,
            'groups' => $groups,
            'preselectedGroupId' => $request->query('group_id'),
            'preselectedFriendId' => $request->query('friend_id'),
        ]);
    }

    /**
     * Store a new expense with its split participants.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        // Authorization checks
        if ($validated['group_id']) {
            $group = Group::findOrFail($validated['group_id']);
            if (! $group->members()->where('user_id', $user->id)->exists()) {
                abort(403, 'You are not a member of this group.');
            }
        }

        // Verify paid_by is a participant
        $participantIds = collect($validated['participants'])->pluck('user_id')->toArray();
        if (! in_array($validated['paid_by'], $participantIds)) {
            return back()->withErrors(['paid_by' => 'The payer must be a participant.']);
        }

        // Calculate splits using SplitCalculator
        $amount = (float) $validated['amount'];
        $splitType = $validated['split_type'];

        try {
            $splits = match ($splitType) {
                'equal' => SplitCalculator::equal($amount, $participantIds),
                'shares' => SplitCalculator::byShares($amount, collect($validated['participants'])->mapWithKeys(
                    fn ($p) => [$p['user_id'] => $p['share_value'] ?? 1]
                )->all()),
                'percentage' => SplitCalculator::byPercentage($amount, collect($validated['participants'])->mapWithKeys(
                    fn ($p) => [$p['user_id'] => $p['share_value'] ?? 0]
                )->all()),
                'exact' => SplitCalculator::byExact($amount, collect($validated['participants'])->mapWithKeys(
                    fn ($p) => [$p['user_id'] => $p['amount'] ?? 0]
                )->all()),
            };
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['split' => $e->getMessage()]);
        }

        // Create expense in a transaction
        $expense = DB::transaction(function () use ($validated, $user, $splits, $splitType) {
            $expense = Expense::create([
                'group_id' => $validated['group_id'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'paid_by' => $validated['paid_by'],
                'split_type' => $splitType,
                'expense_date' => $validated['expense_date'],
                'created_by' => $user->id,
            ]);

            foreach ($splits as $userId => $owedAmount) {
                $participantData = collect($validated['participants'])
                    ->firstWhere('user_id', $userId);

                ExpenseParticipant::create([
                    'expense_id' => $expense->id,
                    'user_id' => $userId,
                    'share_value' => $participantData['share_value'] ?? null,
                    'owed_amount' => $owedAmount,
                ]);
            }

            return $expense;
        });

        if ($validated['group_id']) {
            return redirect()->route('groups.show', $validated['group_id'])
                ->with('success', 'Expense added!');
        }

        return redirect()->route('dashboard')->with('success', 'Expense added!');
    }

    /**
     * Show expense detail.
     */
    public function show(Expense $expense): Response
    {
        // User must be a participant or the payer
        $userId = auth()->id();
        $isParticipant = $expense->participants()->where('user_id', $userId)->exists();
        if (! $isParticipant && $expense->paid_by !== $userId) {
            abort(403);
        }

        $expense->load(['payer:id,name,username', 'participants.user:id,name,username', 'group:id,name']);

        return inertia('Expenses/Show', ['expense' => $expense]);
    }

    /**
     * Delete an expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->created_by !== auth()->id()) {
            abort(403, 'Only the creator can delete this expense.');
        }

        $expense->delete(); // cascade deletes participants

        return redirect()->route('dashboard')->with('success', 'Expense deleted.');
    }
}
