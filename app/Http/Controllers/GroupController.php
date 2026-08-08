<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\BalanceCalculator;
use App\Services\DebtSimplifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GroupController extends Controller
{
    /**
     * List the user's groups.
     */
    public function index(): Response
    {
        $groups = auth()->user()->groups()
            ->withCount('members')
            ->with('members:id,name,username')
            ->latest()
            ->get();

        return inertia('Groups/Index', ['groups' => $groups]);
    }

    /**
     * Show the create group form.
     */
    public function create(): Response
    {
        $friends = collect(auth()->user()->friends())->map(fn ($f) => [
            'id' => $f['id'],
            'name' => $f['name'],
            'username' => $f['username'],
        ])->values();

        return inertia('Groups/Create', ['friends' => $friends]);
    }

    /**
     * Create a new group.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        // Verify all members are in user's circle
        $user = auth()->user();
        $friendIds = collect($user->friends())->pluck('id')->toArray();

        $invalidMembers = array_diff($request->member_ids, $friendIds);
        if (! empty($invalidMembers)) {
            return back()->withErrors(['member_ids' => 'All members must be in your Circle.']);
        }

        $group = Group::create([
            'name' => $request->name,
            'created_by' => $user->id,
        ]);

        // Add creator + selected members
        $group->members()->attach($user->id);
        $group->members()->attach($request->member_ids);

        return redirect()->route('groups.show', $group)->with('success', 'Group created!');
    }

    /**
     * Show group detail with balances and simplified debts.
     */
    public function show(Group $group): Response
    {
        // Authorization: must be a member
        if (! $group->members()->where('user_id', auth()->id())->exists()) {
            abort(403);
        }

        $group->load('members:id,name,username');

        $expenses = $group->expenses()
            ->with(['payer:id,name', 'participants.user:id,name'])
            ->latest('expense_date')
            ->paginate(20);

        $groupBalances = BalanceCalculator::getGroupBalances($group->id);
        $simplifiedDebts = DebtSimplifier::simplify($groupBalances);

        // Enrich with user data
        $memberMap = $group->members->keyBy('id');
        $balancesWithUsers = collect($groupBalances)->map(function ($amount, $userId) use ($memberMap) {
            return [
                'user' => $memberMap[$userId] ?? null,
                'amount' => $amount,
            ];
        })->values();

        $debtsWithUsers = collect($simplifiedDebts)->map(function ($debt) use ($memberMap) {
            return [
                'from' => $memberMap[$debt['from']] ?? null,
                'to' => $memberMap[$debt['to']] ?? null,
                'amount' => $debt['amount'],
            ];
        });

        $memberIds = $group->members->pluck('id')->toArray();
        $addableFriends = collect(auth()->user()->friends())
            ->filter(fn ($f) => ! in_array($f['id'], $memberIds))
            ->map(fn ($f) => [
                'id' => $f['id'],
                'name' => $f['name'],
                'username' => $f['username'],
            ])
            ->values();

        return inertia('Groups/Show', [
            'group' => $group,
            'expenses' => $expenses,
            'balances' => $balancesWithUsers,
            'simplifiedDebts' => $debtsWithUsers,
            'addableFriends' => $addableFriends,
        ]);
    }

    /**
     * Add a member to the group.
     */
    public function addMember(Request $request, Group $group): RedirectResponse
    {
        if ($group->created_by !== auth()->id()) {
            abort(403, 'Only the group creator can add members.');
        }

        $request->validate(['user_id' => 'required|exists:users,id']);

        // Verify user is in creator's circle
        $friendIds = collect(auth()->user()->friends())->pluck('id')->toArray();
        if (! in_array($request->user_id, $friendIds)) {
            return back()->withErrors(['user_id' => 'User must be in your Circle.']);
        }

        $group->members()->syncWithoutDetaching([$request->user_id]);

        return back()->with('success', 'Member added!');
    }

    /**
     * Remove a member from the group.
     */
    public function removeMember(Group $group, User $user): RedirectResponse
    {
        if ($group->created_by !== auth()->id()) {
            abort(403);
        }

        if ($user->id === $group->created_by) {
            return back()->withErrors(['error' => 'Cannot remove the group creator.']);
        }

        $group->members()->detach($user->id);

        return back()->with('success', 'Member removed.');
    }
}
