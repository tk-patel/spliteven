<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SettlementController extends Controller
{
    /**
     * List the user's settlements.
     */
    public function index(): Response
    {
        $userId = auth()->id();

        $settlements = Settlement::where('payer_id', $userId)
            ->orWhere('payee_id', $userId)
            ->with(['payer:id,name,username', 'payee:id,name,username', 'group:id,name'])
            ->latest('settled_at')
            ->paginate(20);

        return inertia('Settlements/Index', ['settlements' => $settlements]);
    }

    /**
     * Show the settle-up form.
     */
    public function create(Request $request): Response
    {
        $friends = collect(auth()->user()->friends())->map(fn ($f) => [
            'id' => $f['id'],
            'name' => $f['name'],
            'username' => $f['username'],
        ])->values();

        $groups = auth()->user()->groups()->get(['id', 'name']);

        return inertia('Settlements/Create', [
            'friends' => $friends,
            'groups' => $groups,
            'preselectedPayeeId' => $request->query('payee_id'),
            'preselectedAmount' => $request->query('amount'),
            'preselectedGroupId' => $request->query('group_id'),
        ]);
    }

    /**
     * Record a payment between users.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'payee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'group_id' => 'nullable|exists:groups,id',
            'note' => 'nullable|string|max:255',
            'settled_at' => 'required|date',
        ]);

        $user = auth()->user();

        // Cannot pay yourself
        if ($request->payee_id == $user->id) {
            return back()->withErrors(['payee_id' => 'Cannot settle with yourself.']);
        }

        Settlement::create([
            'payer_id' => $user->id,
            'payee_id' => $request->payee_id,
            'group_id' => $request->group_id,
            'amount' => $request->amount,
            'note' => $request->note,
            'settled_at' => $request->settled_at,
        ]);

        return redirect()->route('dashboard')->with('success', 'Payment recorded!');
    }
}
