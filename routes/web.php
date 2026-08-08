<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SettlementController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::inertia('/', 'Welcome')->name('home');

Route::post('/check-username', function (Request $request) {
    $request->validate(['username' => 'required|string|min:3|max:30']);

    $exists = User::where('username', Str::lower($request->username))->exists();

    return response()->json(['available' => ! $exists]);
})->name('check-username');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Circle (Friends)
    Route::prefix('circle')->name('circle.')->group(function () {
        Route::get('/', [CircleController::class, 'index'])->name('index');
        Route::post('/search', [CircleController::class, 'search'])->name('search');
        Route::post('/invite/{user}', [CircleController::class, 'invite'])->name('invite');
        Route::post('/accept/{friendship}', [CircleController::class, 'accept'])->name('accept');
        Route::post('/reject/{friendship}', [CircleController::class, 'reject'])->name('reject');
        Route::delete('/{friendship}', [CircleController::class, 'remove'])->name('remove');
    });

    // Groups
    Route::resource('groups', GroupController::class)->except(['edit']);
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
    Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');

    // Expenses
    Route::resource('expenses', ExpenseController::class)->except(['edit']);

    // Settlements
    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::get('/settlements/create', [SettlementController::class, 'create'])->name('settlements.create');
    Route::post('/settlements', [SettlementController::class, 'store'])->name('settlements.store');

    // Activity
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
});

require __DIR__.'/settings.php';
