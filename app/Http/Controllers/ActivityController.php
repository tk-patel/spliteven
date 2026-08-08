<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Settlement;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Show the activity feed (expenses and settlements in chronological order).
     */
    public function index(): Response
    {
        $userId = auth()->id();

        // Get expenses where user is payer or participant
        $expenses = Expense::where('paid_by', $userId)
            ->orWhereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['payer:id,name', 'group:id,name', 'participants.user:id,name'])
            ->get()
            ->map(fn ($e) => [
                'type' => 'expense',
                'id' => $e->id,
                'data' => $e,
                'date' => $e->expense_date->toDateString(),
                'created_at' => $e->created_at,
            ]);

        // Get settlements where user is payer or payee
        $settlements = Settlement::where('payer_id', $userId)
            ->orWhere('payee_id', $userId)
            ->with(['payer:id,name', 'payee:id,name', 'group:id,name'])
            ->get()
            ->map(fn ($s) => [
                'type' => 'settlement',
                'id' => $s->id,
                'data' => $s,
                'date' => $s->settled_at->toDateString(),
                'created_at' => $s->created_at,
            ]);

        // Merge, sort by created_at desc, paginate manually
        $activities = collect($expenses)->merge(collect($settlements))
            ->sortByDesc('created_at')
            ->values()
            ->take(50);

        return inertia('Activity/Index', ['activities' => $activities]);
    }
}
