<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CircleController extends Controller
{
    /**
     * Show the user's circle: friends list and pending requests.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $friends = Friendship::where(function ($q) use ($user) {
            $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id);
        })
            ->where('status', 'accepted')
            ->with(['requester:id,name,username', 'addressee:id,name,username'])
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => [
                'friendship_id' => $friendship->id,
                'id' => $friendship->requester_id === $user->id
                    ? $friendship->addressee_id
                    : $friendship->requester_id,
                'name' => $friendship->requester_id === $user->id
                    ? $friendship->addressee->name
                    : $friendship->requester->name,
                'username' => $friendship->requester_id === $user->id
                    ? $friendship->addressee->username
                    : $friendship->requester->username,
            ])
            ->values();

        $pendingReceived = Friendship::where('addressee_id', $user->id)
            ->where('status', 'pending')
            ->with('requester:id,name,username')
            ->latest()
            ->get();

        $pendingSent = Friendship::where('requester_id', $user->id)
            ->where('status', 'pending')
            ->with('addressee:id,name,username')
            ->latest()
            ->get();

        return inertia('Circle/Index', [
            'friends' => $friends,
            'pendingReceived' => $pendingReceived,
            'pendingSent' => $pendingSent,
        ]);
    }

    /**
     * Search for users by username.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:2|max:30']);

        $user = auth()->user();

        $results = User::where('username', 'like', '%'.$request->query('query').'%')
            ->where('id', '!=', $user->id)
            ->limit(10)
            ->get(['id', 'name', 'username']);

        $results = $results->map(function ($result) use ($user) {
            $friendship = Friendship::where(function ($q) use ($user, $result) {
                $q->where('requester_id', $user->id)->where('addressee_id', $result->id);
            })->orWhere(function ($q) use ($user, $result) {
                $q->where('requester_id', $result->id)->where('addressee_id', $user->id);
            })->first();

            return [
                'id' => $result->id,
                'name' => $result->name,
                'username' => $result->username,
                'friendship_status' => $friendship?->status,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Send a friend request.
     */
    public function invite(User $user): RedirectResponse
    {
        $authUser = auth()->user();

        // Prevent self-invite
        if ($authUser->id === $user->id) {
            return back()->withErrors(['error' => 'Cannot invite yourself.']);
        }

        // Check if friendship already exists (in either direction)
        $existing = Friendship::where(function ($q) use ($authUser, $user) {
            $q->where('requester_id', $authUser->id)->where('addressee_id', $user->id);
        })->orWhere(function ($q) use ($authUser, $user) {
            $q->where('requester_id', $user->id)->where('addressee_id', $authUser->id);
        })->first();

        if ($existing) {
            return back()->withErrors(['error' => 'Friend request already exists.']);
        }

        Friendship::create([
            'requester_id' => $authUser->id,
            'addressee_id' => $user->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Friend request sent!');
    }

    /**
     * Accept a friend request.
     */
    public function accept(Friendship $friendship): RedirectResponse
    {
        // Only the addressee can accept
        if ($friendship->addressee_id !== auth()->id()) {
            abort(403);
        }

        $friendship->update(['status' => 'accepted']);

        return back()->with('success', 'Friend request accepted!');
    }

    /**
     * Reject a friend request.
     */
    public function reject(Friendship $friendship): RedirectResponse
    {
        if ($friendship->addressee_id !== auth()->id()) {
            abort(403);
        }

        $friendship->update(['status' => 'rejected']);

        return back()->with('success', 'Friend request rejected.');
    }

    /**
     * Remove a friend.
     */
    public function remove(Friendship $friendship): RedirectResponse
    {
        $user = auth()->user();

        if ($friendship->requester_id !== $user->id && $friendship->addressee_id !== $user->id) {
            abort(403);
        }

        $friendship->delete();

        return back()->with('success', 'Friend removed.');
    }
}
