# SplitEven — Master Plan

> **Purpose**: This document is the single source of truth for building SplitEven, a Splitwise-style expense-splitting web app. It is written for an AI coding agent (local LLM) to follow phase by phase. Every complex calculation is pre-written as copy-paste-ready code. The agent's job is assembly, wiring, and UI — not algorithm design.

---

## 1. Project Overview

**SplitEven** is a mobile-first responsive web app for tracking shared expenses between friends. Users create accounts, connect with friends via username search, split expenses 1-on-1 or in groups, and settle debts.

### Core Features (MVP)
- Email + unique username registration
- Friend system ("Circle") — search, invite, accept/reject
- 1-on-1 expense sharing
- Group expense sharing (add Circle members to groups)
- Four split types: equal, by shares, by percentage, by exact amounts
- Payment/settlement recording
- Simplified debt balancing
- Dashboard with net balances
- Activity feed

### Explicit Non-Goals (MVP)
- Multi-currency support (single currency per user preference)
- Multiple payers per expense
- Expense categories/tags
- Receipt/image uploads
- Email or push notifications
- Real-time updates (WebSocket)
- OAuth/social login

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend bridge | Inertia.js |
| Frontend | Vue 3 (Composition API + `<script setup>`) |
| CSS | Tailwind CSS 4 |
| Components | shadcn-vue (Radix Vue primitives) |
| Database | MySQL 8+ |
| Testing | PHPUnit (backend), Vitest (frontend unit) |
| Build | Vite |

### Key Conventions
- **All Vue components**: use `<script setup>` with Composition API
- **All PHP**: strict types, type hints on all parameters and returns
- **Money**: stored as `DECIMAL(12,2)`, never float in calculations — use `bcmath` or cast to string for precision
- **API**: all routes return Inertia responses (no separate API — Inertia handles JSON/HTML automatically)
- **Mobile-first**: all CSS starts with mobile styles, use `md:` and `lg:` breakpoints to scale up

---

## 3. Database Schema

### 3.1 ER Diagram (text)

```
users 1──M friendships (requester_id, addressee_id)
users 1──M group_members M──1 groups
users 1──M expenses (paid_by)
users 1──M expense_participants (user_id)
expenses 1──M expense_participants
users 1──M settlements (payer_id, payee_id)
groups 1──M expenses (group_id, nullable)
groups 1──M settlements (group_id, nullable)
```

### 3.2 Migration Code

> **AGENT**: Create each migration file as listed. Use `php artisan make:migration <name>` then replace the content.

#### Migration 1: Modify users table — `add_username_to_users_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->unique()->after('name');
            $table->string('currency', 3)->default('CAD')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'currency']);
        });
    }
};
```

#### Migration 2: `create_friendships_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
            $table->unique(['requester_id', 'addressee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
```

#### Migration 3: `create_groups_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
```

#### Migration 4: `create_group_members_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
```

#### Migration 5: `create_expenses_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
            $table->enum('split_type', ['equal', 'shares', 'percentage', 'exact'])->default('equal');
            $table->date('expense_date');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
```

#### Migration 6: `create_expense_participants_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('share_value', 12, 4)->nullable();
            $table->decimal('owed_amount', 12, 2);
            $table->timestamps();
            $table->unique(['expense_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_participants');
    }
};
```

#### Migration 7: `create_settlements_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->date('settled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
```

### 3.3 Column Reference

| Table | Column | Type | Notes |
|---|---|---|---|
| `users` | `username` | `VARCHAR(30) UNIQUE` | Lowercase, alphanumeric + underscores only |
| `users` | `currency` | `VARCHAR(3)` | ISO 4217 code, default `CAD` |
| `friendships` | `status` | `ENUM` | `pending`, `accepted`, `rejected` |
| `expenses` | `group_id` | `NULLABLE FK` | NULL = 1-on-1 expense (no group) |
| `expenses` | `split_type` | `ENUM` | `equal`, `shares`, `percentage`, `exact` |
| `expense_participants` | `share_value` | `DECIMAL(12,4) NULL` | Stores share count or percentage value |
| `expense_participants` | `owed_amount` | `DECIMAL(12,2)` | Final calculated amount this person owes |
| `settlements` | `payer_id` | `FK` | Person who PAID money (reducing their debt) |
| `settlements` | `payee_id` | `FK` | Person who RECEIVED money |

---

## 4. Eloquent Models

### User Model (modify existing)

Add to the existing `User` model:

```php
// Add to $fillable array:
'username', 'currency'

// Add relationships:
public function friendshipsSent(): HasMany
{
    return $this->hasMany(Friendship::class, 'requester_id');
}

public function friendshipsReceived(): HasMany
{
    return $this->hasMany(Friendship::class, 'addressee_id');
}

public function friends(): Collection
{
    $sent = $this->friendshipsSent()
        ->where('status', 'accepted')
        ->with('addressee')
        ->get()
        ->pluck('addressee');

    $received = $this->friendshipsReceived()
        ->where('status', 'accepted')
        ->with('requester')
        ->get()
        ->pluck('requester');

    return $sent->merge($received);
}

public function groups(): BelongsToMany
{
    return $this->belongsToMany(Group::class, 'group_members')->withTimestamps();
}

public function expensesPaid(): HasMany
{
    return $this->hasMany(Expense::class, 'paid_by');
}

public function expenseParticipations(): HasMany
{
    return $this->hasMany(ExpenseParticipant::class);
}

public function settlementsPaid(): HasMany
{
    return $this->hasMany(Settlement::class, 'payer_id');
}

public function settlementsReceived(): HasMany
{
    return $this->hasMany(Settlement::class, 'payee_id');
}
```

### Friendship Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }
}
```

### Group Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['name', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')->withTimestamps();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }
}
```

### Expense Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    protected $fillable = [
        'group_id', 'description', 'amount', 'paid_by',
        'split_type', 'expense_date', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExpenseParticipant::class);
    }
}
```

### ExpenseParticipant Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseParticipant extends Model
{
    protected $fillable = ['expense_id', 'user_id', 'share_value', 'owed_amount'];

    protected $casts = [
        'share_value' => 'decimal:4',
        'owed_amount' => 'decimal:2',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Settlement Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $fillable = ['payer_id', 'payee_id', 'group_id', 'amount', 'note', 'settled_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_at' => 'date',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
```

---

## 5. Service Classes — PRE-WRITTEN CODE

> **AGENT**: Copy these files exactly into `app/Services/`. Do NOT modify the logic. These are the pre-built, tested calculation engines.

### 5.1 SplitCalculator — `app/Services/SplitCalculator.php`

```php
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
     * @param float $totalAmount The total expense amount
     * @param array<int> $participantIds Array of user IDs
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
     * @param float $totalAmount
     * @param array<int, float> $participantShares [user_id => share_count]
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
     * @param float $totalAmount
     * @param array<int, float> $participantPercentages [user_id => percentage]
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
     * @param float $totalAmount
     * @param array<int, float> $participantAmounts [user_id => exact_amount]
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
```

### 5.2 BalanceCalculator — `app/Services/BalanceCalculator.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
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
     * @param int $userId
     * @param int $otherUserId
     * @param int|null $groupId  If provided, only considers expenses/settlements in that group
     * @return float
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
     * @param int $userId
     * @return array<int, float> [other_user_id => net_balance]
     *   Positive = auth user owes them
     *   Negative = they owe auth user
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
     * @param int $groupId
     * @return array<int, float> [user_id => net_balance_in_group]
     *   Positive = this user is owed money
     *   Negative = this user owes money
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

            // Note: the sign convention for group balances is:
            //   positive = others owe THIS user (this user is a creditor)
            //   negative = THIS user owes others (this user is a debtor)
            // This is OPPOSITE from getNetBalance which is pairwise.

            if (abs($net) > 0.005) {
                $balances[(int) $uid] = $net;
            }
        }

        return $balances;
    }
}
```

### 5.3 DebtSimplifier — `app/Services/DebtSimplifier.php`

```php
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
     * @param array<int, float> $balances [user_id => net_balance]
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
                    'to'   => $creditors[$j]['user_id'],  // to this person
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
```

---

## 6. Routes

> **AGENT**: Define all routes in `routes/web.php`. All routes use Inertia and require authentication middleware.

```php
use App\Http\Controllers\CircleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ActivityController;

// Dashboard
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
```

---

## 7. Vue Pages & Components Specification

### 7.1 Layout — `resources/js/Layouts/AppLayout.vue`

Mobile-first layout with bottom navigation bar.

```
STRUCTURE:
┌──────────────────────────────────┐
│ <slot />  (page content)         │
│ Scrollable area, padding-bottom  │
│ to account for bottom nav height │
│                                  │
│                                  │
├──────────────────────────────────┤
│ Bottom Navigation Bar (fixed)    │
│ [Home] [Circle] [+Add] [Groups] [Activity] │
└──────────────────────────────────┘

On desktop (md+):
┌──────────┬───────────────────────┐
│ Sidebar  │ <slot /> content      │
│ Nav      │                       │
│ links    │                       │
│          │                       │
└──────────┴───────────────────────┘
```

**Props**: None (uses Inertia's `$page.props.auth.user`)

**Bottom Nav Items**:
| Icon | Label | Route | Route Name |
|---|---|---|---|
| Home icon | Home | /dashboard | dashboard |
| Users icon | Circle | /circle | circle.index |
| PlusCircle icon | Add | /expenses/create | expenses.create |
| Layers icon | Groups | /groups | groups.index |
| Activity icon | Activity | /activity | activity.index |

Use `lucide-vue-next` for icons (comes with shadcn-vue).

The "Add" button should be visually prominent — larger, colored with the primary accent.

### 7.2 Pages

#### Dashboard — `Pages/Dashboard.vue`

**Props from controller** (via Inertia):
```typescript
{
  balances: Array<{
    user: { id: number, name: string, username: string },
    amount: number  // positive = you owe them, negative = they owe you
  }>,
  totalOwed: number,    // total you owe others
  totalOwing: number,   // total others owe you
  netBalance: number    // totalOwing - totalOwed
}
```

**Renders**:
- Summary card at top: "You owe $X" (red) / "You are owed $X" (green) / "All settled up" (neutral)
- List of balance cards, each showing: avatar placeholder, name, amount with color coding
- Tapping a balance card navigates to settling up or viewing expenses with that person

#### Circle — `Pages/Circle/Index.vue`

**Props**:
```typescript
{
  friends: Array<{ id: number, name: string, username: string }>,
  pendingReceived: Array<{ id: number, requester: { id: number, name: string, username: string }, created_at: string }>,
  pendingSent: Array<{ id: number, addressee: { id: number, name: string, username: string }, created_at: string }>
}
```

**Renders**:
- Search bar at top (triggers search modal/sheet)
- "Pending Requests" section (if any) with Accept/Reject buttons
- "Your Circle" list of friends
- Empty state: "Search by username to add friends"

**Search**: Use a bottom sheet (mobile) or dialog (desktop). POST to `/circle/search` with `{ query: string }`. Returns matching users. Show "Invite" button next to each.

#### Groups — `Pages/Groups/Index.vue`

**Props**:
```typescript
{
  groups: Array<{
    id: number, name: string, created_by: number,
    members_count: number, members: Array<{ id: number, name: string }>
  }>
}
```

**Renders**:
- "Create Group" button at top
- List of group cards (name, member count, member avatars)
- Tapping a group navigates to group detail

#### Group Detail — `Pages/Groups/Show.vue`

**Props**:
```typescript
{
  group: { id: number, name: string, created_by: number, members: Array<User> },
  expenses: Array<Expense>,
  balances: Array<{ user: User, amount: number }>,
  simplifiedDebts: Array<{ from: User, to: User, amount: number }>
}
```

**Renders**:
- Group name header with edit/settings
- Member list with "Add member" button
- Balance summary per member
- Simplified debts ("Alice pays Bob $25")
- Expense list for this group
- "Add expense" and "Settle up" buttons

#### Add Expense — `Pages/Expenses/Create.vue`

**Props**:
```typescript
{
  friends: Array<{ id: number, name: string, username: string }>,
  groups: Array<{ id: number, name: string, members: Array<User> }>,
  preselectedGroupId?: number,
  preselectedFriendId?: number
}
```

**This is the most complex page. Breakdown:**

1. **Description** field (text input)
2. **Amount** field (number input, currency prefix)
3. **Date** field (date picker, defaults to today)
4. **Context selector**: "With friend" or "In group"
   - If friend: dropdown to select one friend from Circle
   - If group: dropdown to select a group → participants auto-populate from group members
5. **Paid by**: dropdown showing current user + other participants. Defaults to current user.
6. **Split type selector**: tabs or segmented control → Equal | Shares | Percentage | Exact
7. **Split detail area** (changes based on split type):
   - **Equal**: just show "Split equally among N people — $X.XX each"
   - **Shares**: each participant has a number input for their share count (default 1)
   - **Percentage**: each participant has a number input for their %. Show running total. Must = 100.
   - **Exact**: each participant has a currency input. Show remaining amount. Must = total.
8. **Save button**

**Validation feedback**: show inline errors. Amounts that don't sum correctly should be highlighted.

**CRITICAL**: The split preview (showing each person's calculated amount) must update reactively as the user changes values. Use Vue `computed` properties for this.

#### Expense Detail — `Pages/Expenses/Show.vue`

**Props**:
```typescript
{
  expense: {
    id: number, description: string, amount: number,
    paid_by: User, split_type: string, expense_date: string,
    group?: Group,
    participants: Array<{ user: User, owed_amount: number, share_value?: number }>
  }
}
```

#### Settle Up — `Pages/Settlements/Create.vue`

**Props**:
```typescript
{
  friends: Array<{ id: number, name: string, username: string }>,
  groups: Array<{ id: number, name: string }>,
  preselectedPayeeId?: number,
  preselectedAmount?: number,
  preselectedGroupId?: number
}
```

**Renders**:
- "You paid" → current user (fixed)
- "Paid to" → select from friends
- "Amount" → number input (pre-filled if coming from balance)
- "Group" → optional, select group or "No group (personal)"
- "Date" → date picker
- "Note" → optional text
- Save button

#### Activity — `Pages/Activity/Index.vue`

**Props**:
```typescript
{
  activities: Array<{
    type: 'expense' | 'settlement',
    data: Expense | Settlement,
    created_at: string
  }>
}
```

**Renders**: Chronological feed of expenses and settlements with human-readable descriptions. Paginated (use Inertia pagination).

### 7.3 Shared Components

| Component | Description | Location |
|---|---|---|
| `BottomNav.vue` | Mobile bottom navigation | `Components/BottomNav.vue` |
| `BalanceCard.vue` | Shows balance with one person | `Components/BalanceCard.vue` |
| `ExpenseListItem.vue` | One row in an expense list | `Components/ExpenseListItem.vue` |
| `SplitForm.vue` | The split type selector + per-person inputs | `Components/SplitForm.vue` |
| `UserAvatar.vue` | Colored circle with initials | `Components/UserAvatar.vue` |
| `EmptyState.vue` | Illustration + message for empty lists | `Components/EmptyState.vue` |
| `SearchSheet.vue` | Bottom sheet for username search | `Components/SearchSheet.vue` |
| `AmountDisplay.vue` | Formats and color-codes money amounts | `Components/AmountDisplay.vue` |

---

## 8. Implementation Phases

> **AGENT**: Execute one phase at a time. After completing each phase, update `PROGRESS.md`. If you encounter an error, document it in PROGRESS.md and attempt to fix. Each phase starts with "READ PROGRESS.md" and ends with "UPDATE PROGRESS.md".

---

### Phase 0: Project Setup & Dependencies

**Objective**: Verify the Laravel project, install frontend dependencies, configure shadcn-vue.

**Prerequisites**: Blank Laravel 12 project with Inertia + Vue created via `laravel new spliteven` or equivalent.

**Steps**:

1. Verify project structure exists:
   ```bash
   ls -la
   php artisan --version
   node --version
   npm --version
   ```

2. Install shadcn-vue:
   ```bash
   npx shadcn-vue@latest init
   ```
   When prompted:
   - Style: Default
   - Base color: Slate
   - CSS path: `resources/css/app.css`
   - Tailwind config: (accept default or point to your tailwind config)
   - Components alias: `@/Components/ui`
   - Utils alias: `@/lib/utils`

3. Install required shadcn-vue components:
   ```bash
   npx shadcn-vue@latest add button
   npx shadcn-vue@latest add input
   npx shadcn-vue@latest add label
   npx shadcn-vue@latest add card
   npx shadcn-vue@latest add dialog
   npx shadcn-vue@latest add sheet
   npx shadcn-vue@latest add select
   npx shadcn-vue@latest add tabs
   npx shadcn-vue@latest add avatar
   npx shadcn-vue@latest add badge
   npx shadcn-vue@latest add separator
   npx shadcn-vue@latest add toast
   npx shadcn-vue@latest add dropdown-menu
   npx shadcn-vue@latest add alert
   ```

4. Install lucide icons:
   ```bash
   npm install lucide-vue-next
   ```

5. Set up `.env` with database credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=spliteven
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. Create the database:
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS spliteven;"
   ```

7. Verify everything compiles:
   ```bash
   npm run build
   php artisan migrate
   ```

**Verification**:
- `npm run build` succeeds with no errors
- `php artisan migrate` runs the default Laravel migrations
- Visiting the app shows the default Laravel welcome/login page

**Update PROGRESS.md**: Mark Phase 0 complete.

---

### Phase 1: Database Migrations

**Objective**: Create all 7 migrations from Section 3.2.

**Steps**:

1. Generate migration files (run each command, then replace the generated content with the code from Section 3.2):
   ```bash
   php artisan make:migration add_username_to_users_table --table=users
   php artisan make:migration create_friendships_table --create=friendships
   php artisan make:migration create_groups_table --create=groups
   php artisan make:migration create_group_members_table --create=group_members
   php artisan make:migration create_expenses_table --create=expenses
   php artisan make:migration create_expense_participants_table --create=expense_participants
   php artisan make:migration create_settlements_table --create=settlements
   ```

2. Replace each migration's content with the exact code from Section 3.2.

3. Run migrations:
   ```bash
   php artisan migrate
   ```

**Verification**:
```bash
php artisan migrate:status
# All migrations should show "Ran"

# Verify table structure:
php artisan tinker --execute="Schema::getColumnListing('users')"
# Should include: username, currency

php artisan tinker --execute="Schema::getColumnListing('friendships')"
# Should include: requester_id, addressee_id, status

php artisan tinker --execute="Schema::getColumnListing('expenses')"
# Should include: group_id, description, amount, paid_by, split_type, expense_date, created_by

php artisan tinker --execute="Schema::getColumnListing('expense_participants')"
# Should include: expense_id, user_id, share_value, owed_amount
```

**Update PROGRESS.md**: Mark Phase 1 complete, note any issues.

---

### Phase 2: Models & Relationships

**Objective**: Create all Eloquent models from Section 4 and add model factories.

**Steps**:

1. Create model files:
   ```bash
   php artisan make:model Friendship
   php artisan make:model Group
   php artisan make:model ExpenseParticipant
   php artisan make:model Expense
   php artisan make:model Settlement
   ```

2. Replace each model's content with the exact code from Section 4.

3. Modify the existing `User` model (`app/Models/User.php`):
   - Add `username` and `currency` to `$fillable`
   - Add all the relationship methods from Section 4
   - Add these imports at the top:
     ```php
     use Illuminate\Database\Eloquent\Relations\HasMany;
     use Illuminate\Database\Eloquent\Relations\BelongsToMany;
     use Illuminate\Support\Collection;
     ```

4. Create factories for testing:

   **`database/factories/UserFactory.php`** — modify the existing factory:
   ```php
   // Add to the definition() method's return array:
   'username' => fake()->unique()->regexify('[a-z]{3,15}'),
   'currency' => 'CAD',
   ```

5. Create a `FriendshipFactory`:
   ```bash
   php artisan make:factory FriendshipFactory --model=Friendship
   ```
   ```php
   public function definition(): array
   {
       return [
           'requester_id' => User::factory(),
           'addressee_id' => User::factory(),
           'status' => 'pending',
       ];
   }

   public function accepted(): static
   {
       return $this->state(fn () => ['status' => 'accepted']);
   }
   ```

**Verification**:
```bash
php artisan tinker --execute="
\$u = App\Models\User::factory()->create(['username' => 'testuser1']);
echo 'User created: ' . \$u->username;
\$u2 = App\Models\User::factory()->create(['username' => 'testuser2']);
App\Models\Friendship::create(['requester_id' => \$u->id, 'addressee_id' => \$u2->id, 'status' => 'accepted']);
echo ' Friends: ' . \$u->friends()->count();
"
# Should output: User created: testuser1 Friends: 1
```

Then clean up test data:
```bash
php artisan migrate:fresh
```

**Update PROGRESS.md**: Mark Phase 2 complete.

---

### Phase 3: Authentication Enhancement

**Objective**: Add username field to registration, make it unique and searchable.

**Steps**:

1. Find the registration controller. In Laravel 12 starter kits, it could be:
   - `app/Http/Controllers/Auth/RegisteredUserController.php` (Breeze)
   - Or within the Inertia pages directly

2. Modify the registration validation to include username:
   ```php
   'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', 'unique:users'],
   ```

3. Add username to the `User::create()` call in registration.

4. Modify the Vue registration page (`resources/js/Pages/Auth/Register.vue`):
   - Add a username field between name and email
   - Add client-side validation hint: "lowercase letters, numbers, underscores only"
   - Show real-time availability check (debounced POST to a new route)

5. Add a username availability check route:
   ```php
   // In routes/web.php (outside auth middleware):
   Route::post('/check-username', function (Request $request) {
       $request->validate(['username' => 'required|string|min:3|max:30']);
       $exists = User::where('username', $request->username)->exists();
       return response()->json(['available' => !$exists]);
   })->name('check-username');
   ```

6. Ensure the login still works with email (don't change login flow).

**Verification**:
- Visit `/register`, fill in the form including a username
- Username field shows validation errors for invalid formats
- Registration creates user with username
- Login still works with email/password
- Duplicate username is rejected

**Update PROGRESS.md**: Mark Phase 3 complete.

---

### Phase 4: Circle (Friends) System

**Objective**: Build the friend request system with search, invite, accept, reject, and list.

**Steps**:

1. Create the controller:
   ```bash
   php artisan make:controller CircleController
   ```

2. Implement `CircleController` with these methods:

   **`index()`**: Return Inertia page with friends list and pending requests
   ```php
   public function index(): \Inertia\Response
   {
       $user = auth()->user();

       $friends = $user->friends()->map(fn ($friend) => [
           'id' => $friend->id,
           'name' => $friend->name,
           'username' => $friend->username,
       ]);

       $pendingReceived = Friendship::where('addressee_id', $user->id)
           ->where('status', 'pending')
           ->with('requester:id,name,username')
           ->get();

       $pendingSent = Friendship::where('requester_id', $user->id)
           ->where('status', 'pending')
           ->with('addressee:id,name,username')
           ->get();

       return inertia('Circle/Index', [
           'friends' => $friends,
           'pendingReceived' => $pendingReceived,
           'pendingSent' => $pendingSent,
       ]);
   }
   ```

   **`search(Request $request)`**: Search users by username
   ```php
   public function search(Request $request)
   {
       $request->validate(['query' => 'required|string|min:2|max:30']);
       $user = auth()->user();

       $results = User::where('username', 'like', '%' . $request->query('query') . '%')
           ->where('id', '!=', $user->id)
           ->limit(10)
           ->get(['id', 'name', 'username']);

       // Add friendship status to each result
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
   ```

   **`invite(User $user)`**: Send friend request
   ```php
   public function invite(User $user)
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
   ```

   **`accept(Friendship $friendship)`**: Accept a friend request
   ```php
   public function accept(Friendship $friendship)
   {
       // Only the addressee can accept
       if ($friendship->addressee_id !== auth()->id()) {
           abort(403);
       }

       $friendship->update(['status' => 'accepted']);
       return back()->with('success', 'Friend request accepted!');
   }
   ```

   **`reject(Friendship $friendship)`**: Reject a friend request
   ```php
   public function reject(Friendship $friendship)
   {
       if ($friendship->addressee_id !== auth()->id()) {
           abort(403);
       }

       $friendship->update(['status' => 'rejected']);
       return back()->with('success', 'Friend request rejected.');
   }
   ```

   **`remove(Friendship $friendship)`**: Remove a friend
   ```php
   public function remove(Friendship $friendship)
   {
       $user = auth()->user();
       if ($friendship->requester_id !== $user->id && $friendship->addressee_id !== $user->id) {
           abort(403);
       }

       $friendship->delete();
       return back()->with('success', 'Friend removed.');
   }
   ```

3. Add routes from Section 6 (circle routes).

4. Create `resources/js/Pages/Circle/Index.vue`:
   - Use the AppLayout
   - Search bar at top triggers a Sheet (mobile) or Dialog (desktop) component
   - Pending requests section with Accept/Reject buttons (shadcn Button)
   - Friends list with UserAvatar + name + username
   - Empty state if no friends

5. Create `resources/js/Components/SearchSheet.vue`:
   - Input field for username search
   - Debounced search (300ms) calling `/circle/search` via fetch
   - Results list showing username, name, and "Invite" button
   - Show friendship status (already friends, pending, etc.)

**Verification**:
- Register two users in separate browser sessions
- User A searches for User B by username
- User A sends friend request
- User B sees pending request
- User B accepts → both see each other in their Circle
- User A can remove User B from Circle

**Update PROGRESS.md**: Mark Phase 4 complete.

---

### Phase 5: Groups

**Objective**: Create groups, add/remove members from Circle, view group details.

**Steps**:

1. Create controller:
   ```bash
   php artisan make:controller GroupController
   ```

2. Implement methods:

   **`index()`**: List user's groups
   ```php
   public function index(): \Inertia\Response
   {
       $groups = auth()->user()->groups()
           ->withCount('members')
           ->with('members:id,name,username')
           ->latest()
           ->get();

       return inertia('Groups/Index', ['groups' => $groups]);
   }
   ```

   **`create()`**: Show create form
   ```php
   public function create(): \Inertia\Response
   {
       $friends = auth()->user()->friends()->map(fn ($f) => [
           'id' => $f->id, 'name' => $f->name, 'username' => $f->username,
       ]);

       return inertia('Groups/Create', ['friends' => $friends]);
   }
   ```

   **`store(Request $request)`**: Create group
   ```php
   public function store(Request $request)
   {
       $request->validate([
           'name' => 'required|string|max:255',
           'member_ids' => 'required|array|min:1',
           'member_ids.*' => 'exists:users,id',
       ]);

       // Verify all members are in user's circle
       $user = auth()->user();
       $friendIds = $user->friends()->pluck('id')->toArray();
       $invalidMembers = array_diff($request->member_ids, $friendIds);
       if (!empty($invalidMembers)) {
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
   ```

   **`show(Group $group)`**: Show group detail
   ```php
   public function show(Group $group): \Inertia\Response
   {
       // Authorization: must be a member
       if (!$group->members()->where('user_id', auth()->id())->exists()) {
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

       return inertia('Groups/Show', [
           'group' => $group,
           'expenses' => $expenses,
           'balances' => $balancesWithUsers,
           'simplifiedDebts' => $debtsWithUsers,
       ]);
   }
   ```

   **`addMember(Request $request, Group $group)`**: Add member
   ```php
   public function addMember(Request $request, Group $group)
   {
       if ($group->created_by !== auth()->id()) {
           abort(403, 'Only the group creator can add members.');
       }

       $request->validate(['user_id' => 'required|exists:users,id']);

       // Verify user is in creator's circle
       $friendIds = auth()->user()->friends()->pluck('id')->toArray();
       if (!in_array($request->user_id, $friendIds)) {
           return back()->withErrors(['user_id' => 'User must be in your Circle.']);
       }

       $group->members()->syncWithoutDetaching([$request->user_id]);

       return back()->with('success', 'Member added!');
   }
   ```

   **`removeMember(Group $group, User $user)`**: Remove member
   ```php
   public function removeMember(Group $group, User $user)
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
   ```

3. Create Vue pages:

   **`Pages/Groups/Index.vue`**: Grid of group cards, "Create Group" button.

   **`Pages/Groups/Create.vue`**: Form with group name + multi-select friends from Circle.

   **`Pages/Groups/Show.vue`**: Members, balances, simplified debts, expense list, action buttons.

**Verification**:
- Create a group with 2+ friends
- Group appears in the list
- Group detail shows members
- Add another member
- Remove a member (not the creator)

**Update PROGRESS.md**: Mark Phase 5 complete.

---

### Phase 6: Expense Backend

**Objective**: Copy in the service classes, build the expense controller, handle all 4 split types.

**Steps**:

1. Create service files — copy the EXACT code from Section 5:
   ```
   app/Services/SplitCalculator.php    → Section 5.1
   app/Services/BalanceCalculator.php   → Section 5.2
   app/Services/DebtSimplifier.php      → Section 5.3
   ```

2. Create the controller:
   ```bash
   php artisan make:controller ExpenseController
   ```

3. Create a Form Request for validation:
   ```bash
   php artisan make:request StoreExpenseRequest
   ```

   **`app/Http/Requests/StoreExpenseRequest.php`**:
   ```php
   <?php

   namespace App\Http\Requests;

   use Illuminate\Foundation\Http\FormRequest;
   use Illuminate\Validation\Rule;

   class StoreExpenseRequest extends FormRequest
   {
       public function authorize(): bool
       {
           return true;
       }

       public function rules(): array
       {
           return [
               'description' => ['required', 'string', 'max:255'],
               'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
               'expense_date' => ['required', 'date'],
               'group_id' => ['nullable', 'exists:groups,id'],
               'paid_by' => ['required', 'exists:users,id'],
               'split_type' => ['required', Rule::in(['equal', 'shares', 'percentage', 'exact'])],
               'participants' => ['required', 'array', 'min:2'],
               'participants.*.user_id' => ['required', 'exists:users,id'],
               'participants.*.share_value' => ['nullable', 'numeric', 'min:0'],
               'participants.*.amount' => ['nullable', 'numeric', 'min:0'],
           ];
       }
   }
   ```

4. Implement `ExpenseController`:

   **`create()`**:
   ```php
   public function create(Request $request): \Inertia\Response
   {
       $user = auth()->user();
       $friends = $user->friends()->map(fn ($f) => [
           'id' => $f->id, 'name' => $f->name, 'username' => $f->username,
       ]);

       $groups = $user->groups()->with('members:id,name,username')->get();

       return inertia('Expenses/Create', [
           'friends' => $friends,
           'groups' => $groups,
           'preselectedGroupId' => $request->query('group_id'),
           'preselectedFriendId' => $request->query('friend_id'),
       ]);
   }
   ```

   **`store(StoreExpenseRequest $request)`**:
   ```php
   public function store(StoreExpenseRequest $request)
   {
       $validated = $request->validated();
       $user = auth()->user();

       // Authorization checks
       if ($validated['group_id']) {
           $group = Group::findOrFail($validated['group_id']);
           if (!$group->members()->where('user_id', $user->id)->exists()) {
               abort(403, 'You are not a member of this group.');
           }
       }

       // Verify paid_by is a participant
       $participantIds = collect($validated['participants'])->pluck('user_id')->toArray();
       if (!in_array($validated['paid_by'], $participantIds)) {
           return back()->withErrors(['paid_by' => 'The payer must be a participant.']);
       }

       // Calculate splits using SplitCalculator
       $amount = (float) $validated['amount'];
       $splitType = $validated['split_type'];

       try {
           switch ($splitType) {
               case 'equal':
                   $splits = SplitCalculator::equal($amount, $participantIds);
                   break;

               case 'shares':
                   $shares = [];
                   foreach ($validated['participants'] as $p) {
                       $shares[$p['user_id']] = $p['share_value'] ?? 1;
                   }
                   $splits = SplitCalculator::byShares($amount, $shares);
                   break;

               case 'percentage':
                   $percentages = [];
                   foreach ($validated['participants'] as $p) {
                       $percentages[$p['user_id']] = $p['share_value'] ?? 0;
                   }
                   $splits = SplitCalculator::byPercentage($amount, $percentages);
                   break;

               case 'exact':
                   $amounts = [];
                   foreach ($validated['participants'] as $p) {
                       $amounts[$p['user_id']] = $p['amount'] ?? 0;
                   }
                   $splits = SplitCalculator::byExact($amount, $amounts);
                   break;
           }
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
   ```

   **`show(Expense $expense)`**:
   ```php
   public function show(Expense $expense): \Inertia\Response
   {
       // User must be a participant or the payer
       $userId = auth()->id();
       $isParticipant = $expense->participants()->where('user_id', $userId)->exists();
       if (!$isParticipant && $expense->paid_by !== $userId) {
           abort(403);
       }

       $expense->load(['payer:id,name,username', 'participants.user:id,name,username', 'group:id,name']);

       return inertia('Expenses/Show', ['expense' => $expense]);
   }
   ```

   **`destroy(Expense $expense)`**:
   ```php
   public function destroy(Expense $expense)
   {
       if ($expense->created_by !== auth()->id()) {
           abort(403, 'Only the creator can delete this expense.');
       }

       $expense->delete(); // cascade deletes participants

       return redirect()->route('dashboard')->with('success', 'Expense deleted.');
   }
   ```

5. Add required imports at top of ExpenseController:
   ```php
   use App\Http\Requests\StoreExpenseRequest;
   use App\Models\Expense;
   use App\Models\ExpenseParticipant;
   use App\Models\Group;
   use App\Services\SplitCalculator;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\DB;
   ```

**Verification**:
```bash
# Run the SplitCalculator tests (create them first — see Phase 12 test section)
php artisan tinker --execute="
use App\Services\SplitCalculator;
// Equal split: 100 among 3 people
\$result = SplitCalculator::equal(100.00, [1, 2, 3]);
var_dump(\$result);
// Expected: [1 => '33.33', 2 => '33.33', 3 => '33.34']

// Shares: 100 with shares 2,1,1
\$result = SplitCalculator::byShares(100.00, [1 => 2, 2 => 1, 3 => 1]);
var_dump(\$result);
// Expected: [1 => '50.00', 2 => '25.00', 3 => '25.00']
"
```

**Update PROGRESS.md**: Mark Phase 6 complete.

---

### Phase 7: Expense Frontend (Add Expense Form)

**Objective**: Build the "Add Expense" Vue page with reactive split calculations.

> **NOTE FOR AGENT**: This is the most complex frontend component. Follow the specification exactly. Use Vue 3 Composition API with `<script setup>`. Use `computed` for derived values.

**Steps**:

1. Create `resources/js/Pages/Expenses/Create.vue`

2. The form has these reactive states:
   ```javascript
   const form = useForm({
     description: '',
     amount: '',
     expense_date: new Date().toISOString().split('T')[0],
     group_id: props.preselectedGroupId || null,
     paid_by: page.props.auth.user.id,
     split_type: 'equal',
     participants: [],  // Array of { user_id, name, share_value, amount }
   });
   ```

3. **Context flow**:
   - User selects "With a friend" or "In a group"
   - If friend: select one friend → participants = [authUser, selectedFriend]
   - If group: select group → participants = all group members
   - Either way, `participants` array is populated

4. **Split type logic** (use `computed`):
   ```javascript
   const calculatedSplits = computed(() => {
     const amount = parseFloat(form.amount) || 0;
     const participants = form.participants;
     if (!amount || participants.length < 2) return [];

     switch (form.split_type) {
       case 'equal': {
         const perPerson = amount / participants.length;
         return participants.map((p, i) => ({
           ...p,
           calculatedAmount: i === participants.length - 1
             ? +(amount - perPerson * (participants.length - 1)).toFixed(2)
             : +perPerson.toFixed(2)
         }));
       }
       case 'shares': {
         const totalShares = participants.reduce((sum, p) => sum + (parseFloat(p.share_value) || 1), 0);
         let allocated = 0;
         return participants.map((p, i) => {
           const share = parseFloat(p.share_value) || 1;
           if (i === participants.length - 1) {
             return { ...p, calculatedAmount: +(amount - allocated).toFixed(2) };
           }
           const calc = +((share / totalShares) * amount).toFixed(2);
           allocated += calc;
           return { ...p, calculatedAmount: calc };
         });
       }
       case 'percentage': {
         let allocated = 0;
         return participants.map((p, i) => {
           const pct = parseFloat(p.share_value) || 0;
           if (i === participants.length - 1) {
             return { ...p, calculatedAmount: +(amount - allocated).toFixed(2) };
           }
           const calc = +((pct / 100) * amount).toFixed(2);
           allocated += calc;
           return { ...p, calculatedAmount: calc };
         });
       }
       case 'exact': {
         return participants.map(p => ({
           ...p,
           calculatedAmount: +(parseFloat(p.amount) || 0).toFixed(2)
         }));
       }
       default:
         return [];
     }
   });

   const totalPercentage = computed(() =>
     form.participants.reduce((sum, p) => sum + (parseFloat(p.share_value) || 0), 0)
   );

   const totalExact = computed(() =>
     form.participants.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0)
   );

   const remainingExact = computed(() =>
     +((parseFloat(form.amount) || 0) - totalExact.value).toFixed(2)
   );
   ```

5. **Form submission**: Transform the data to match the backend expectation:
   ```javascript
   function submit() {
     const data = {
       ...form.data(),
       participants: form.participants.map(p => ({
         user_id: p.user_id,
         share_value: form.split_type === 'shares' || form.split_type === 'percentage'
           ? parseFloat(p.share_value) || 0
           : null,
         amount: form.split_type === 'exact'
           ? parseFloat(p.amount) || 0
           : null,
       })),
     };
     form.transform(() => data).post(route('expenses.store'));
   }
   ```

6. **UI Layout** (mobile-first):
   ```
   ┌─────────────────────────────┐
   │ ← Add Expense               │
   ├─────────────────────────────┤
   │ Description                  │
   │ [_________________________]  │
   │                              │
   │ Amount                       │
   │ $ [______________]           │
   │                              │
   │ Date                         │
   │ [2026-08-06        ]         │
   │                              │
   │ ┌─────────┬─────────┐       │
   │ │ Friend  │  Group  │       │  ← Tabs
   │ └─────────┴─────────┘       │
   │ [Select friend ▼]           │
   │                              │
   │ Paid by                      │
   │ [You ▼]                      │
   │                              │
   │ Split type                   │
   │ [Equal][Shares][%][Exact]    │  ← Segmented
   │                              │
   │ ┌──────────────────────────┐ │
   │ │ You          $33.33      │ │
   │ │ Alice        $33.33      │ │
   │ │ Bob          $33.34      │ │
   │ └──────────────────────────┘ │
   │                              │
   │ [     Save Expense     ]     │
   └─────────────────────────────┘
   ```

   For "Shares" mode: each participant row has a number stepper (- [1] +) for their share count.
   For "Percentage" mode: each participant row has a % input. Show total at bottom (must = 100%).
   For "Exact" mode: each participant row has a $ input. Show remaining at bottom (must = $0.00).

7. Create `resources/js/Pages/Expenses/Show.vue` — simple detail page showing the expense info, who paid, split breakdown.

**Verification**:
- Navigate to Add Expense
- Select a friend → 2 participants appear
- Change split type → UI updates accordingly
- Equal: shows per-person amount
- Shares: can adjust shares, amounts recalculate
- Percentage: shows running total
- Exact: shows remaining amount
- Submit creates the expense
- Expense detail page shows correct info

**Update PROGRESS.md**: Mark Phase 7 complete.

---

### Phase 8: Settlements

**Objective**: Record payments between users to settle debts.

**Steps**:

1. Create controller:
   ```bash
   php artisan make:controller SettlementController
   ```

2. Implement:

   **`index()`**: List user's settlements
   ```php
   public function index(): \Inertia\Response
   {
       $userId = auth()->id();

       $settlements = Settlement::where('payer_id', $userId)
           ->orWhere('payee_id', $userId)
           ->with(['payer:id,name,username', 'payee:id,name,username', 'group:id,name'])
           ->latest('settled_at')
           ->paginate(20);

       return inertia('Settlements/Index', ['settlements' => $settlements]);
   }
   ```

   **`create(Request $request)`**:
   ```php
   public function create(Request $request): \Inertia\Response
   {
       $friends = auth()->user()->friends()->map(fn ($f) => [
           'id' => $f->id, 'name' => $f->name, 'username' => $f->username,
       ]);

       $groups = auth()->user()->groups()->get(['id', 'name']);

       return inertia('Settlements/Create', [
           'friends' => $friends,
           'groups' => $groups,
           'preselectedPayeeId' => $request->query('payee_id'),
           'preselectedAmount' => $request->query('amount'),
           'preselectedGroupId' => $request->query('group_id'),
       ]);
   }
   ```

   **`store(Request $request)`**:
   ```php
   public function store(Request $request)
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
   ```

3. Create Vue pages:

   **`Pages/Settlements/Create.vue`**: Simple form with fields as specified in Section 7.2.

**Verification**:
- Record a payment from User A to User B
- Settlement appears in both users' history
- Dashboard balances update correctly after settlement

**Update PROGRESS.md**: Mark Phase 8 complete.

---

### Phase 9: Dashboard & Balances

**Objective**: Build the main dashboard showing all balances and overall financial summary.

**Steps**:

1. Create controller:
   ```bash
   php artisan make:controller DashboardController
   ```

2. Implement:
   ```php
   public function index(): \Inertia\Response
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
           if ($a['amount'] > 0 && $b['amount'] <= 0) return -1;
           if ($a['amount'] <= 0 && $b['amount'] > 0) return 1;
           return abs($b['amount']) <=> abs($a['amount']);
       });

       return inertia('Dashboard', [
           'balances' => $balances,
           'totalOwed' => round($totalOwed, 2),
           'totalOwing' => round($totalOwing, 2),
           'netBalance' => round($totalOwing - $totalOwed, 2),
       ]);
   }
   ```

3. Create `resources/js/Pages/Dashboard.vue`:
   - Summary card at top with color-coded amounts
   - List of BalanceCard components
   - Each card is tappable → goes to "Settle Up" pre-filled

4. Create `resources/js/Components/BalanceCard.vue`:
   ```
   Props: { user: Object, amount: Number }

   Display:
   ┌─────────────────────────────────┐
   │ [Avatar]  Alice                 │
   │           @alice                │
   │           you owe $45.00    →   │  (red text if you owe)
   │           owes you $30.00   →   │  (green text if they owe)
   └─────────────────────────────────┘
   ```

5. Create `resources/js/Components/AmountDisplay.vue`:
   ```
   Props: { amount: Number, showSign: Boolean }
   - If positive: red text, "you owe $X"
   - If negative: green text, "owes you $X"
   - If zero: neutral text, "settled up"
   ```

**Verification**:
- Add expenses between users
- Dashboard shows correct net balances
- Amounts match: if A paid $100 split equally with B, dashboard shows B owes A $50
- After settlement, balances update

**Update PROGRESS.md**: Mark Phase 9 complete.

---

### Phase 10: Activity Feed

**Objective**: Show recent expenses and settlements in chronological order.

**Steps**:

1. Create controller:
   ```bash
   php artisan make:controller ActivityController
   ```

2. Implement:
   ```php
   public function index(): \Inertia\Response
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
       $activities = $expenses->merge($settlements)
           ->sortByDesc('created_at')
           ->values()
           ->take(50);

       return inertia('Activity/Index', ['activities' => $activities]);
   }
   ```

3. Create `resources/js/Pages/Activity/Index.vue`:
   - Chronological list
   - Each item shows: icon (receipt for expense, dollar for settlement), description, amount, date
   - Expense items are tappable → expense detail
   - Group name shown as a badge if applicable

**Verification**:
- Activity feed shows expenses and settlements in chronological order
- Both the payer and participants see the expense in their feed

**Update PROGRESS.md**: Mark Phase 10 complete.

---

### Phase 11: Layout, Navigation & UI Polish

**Objective**: Build the AppLayout with bottom nav, responsive behavior, and polish empty states.

**Steps**:

1. Create `resources/js/Layouts/AppLayout.vue`:

   **Mobile** (default):
   - Full-width content area with `pb-20` (padding for bottom nav)
   - Fixed bottom navigation bar with 5 items
   - The center "Add" button is visually distinct (primary color, slightly raised/larger)

   **Desktop** (`md:` breakpoint and up):
   - Sidebar on the left (240px) with navigation links
   - Content area takes remaining space, max-width 640px centered
   - No bottom nav

   ```vue
   <!-- Pseudocode structure -->
   <template>
     <div class="min-h-screen bg-background">
       <!-- Desktop sidebar (hidden on mobile) -->
       <aside class="hidden md:fixed md:inset-y-0 md:flex md:w-60 md:flex-col border-r">
         <div class="p-4 font-bold text-xl">SplitEven</div>
         <nav class="flex-1 p-2 space-y-1">
           <!-- NavLink for each route -->
         </nav>
         <div class="p-4 border-t">
           <!-- User info + logout -->
         </div>
       </aside>

       <!-- Main content -->
       <main class="md:ml-60">
         <div class="mx-auto max-w-2xl p-4 pb-24 md:pb-4">
           <slot />
         </div>
       </main>

       <!-- Mobile bottom nav (hidden on desktop) -->
       <nav class="fixed bottom-0 inset-x-0 bg-background border-t flex items-center justify-around h-16 md:hidden z-50">
         <!-- 5 nav items -->
       </nav>
     </div>
   </template>
   ```

2. Update ALL pages to use `AppLayout`:
   ```vue
   <script setup>
   import AppLayout from '@/Layouts/AppLayout.vue';
   defineOptions({ layout: AppLayout });
   </script>
   ```
   Or use the Inertia persistent layout pattern.

3. Create `resources/js/Components/EmptyState.vue`:
   ```
   Props: { icon: Component, title: String, description: String }
   Renders: centered icon + text, muted colors
   ```
   Use in: Dashboard (no balances), Circle (no friends), Groups (no groups), Activity (no activity).

4. Create `resources/js/Components/UserAvatar.vue`:
   ```
   Props: { name: String, size: 'sm' | 'md' | 'lg' }
   Renders: colored circle with first initial
   Color is deterministic based on name (hash the name to pick from a preset palette)
   ```

   **Color palette for avatars** (pick by `name.charCodeAt(0) % 8`):
   ```javascript
   const colors = [
     'bg-red-100 text-red-700',
     'bg-blue-100 text-blue-700',
     'bg-green-100 text-green-700',
     'bg-yellow-100 text-yellow-700',
     'bg-purple-100 text-purple-700',
     'bg-pink-100 text-pink-700',
     'bg-indigo-100 text-indigo-700',
     'bg-orange-100 text-orange-700',
   ];
   ```

5. Add page header to each page (simple back arrow + title on mobile, just title on desktop).

6. Style tweaks:
   - All cards: `rounded-lg border bg-card shadow-sm`
   - Spacing: `space-y-4` between sections
   - Touch targets: minimum 44x44px for all interactive elements
   - Font sizes: body text 16px minimum on mobile (prevents iOS zoom on focus)

**Verification**:
- On mobile viewport: bottom nav visible, sidebar hidden, content full-width
- On desktop viewport: sidebar visible, bottom nav hidden, content centered
- All pages render correctly in both viewports
- Empty states show when no data
- Navigation highlights the active route

**Update PROGRESS.md**: Mark Phase 11 complete.

---

### Phase 12: Testing Suite

**Objective**: Write PHPUnit tests for all critical backend logic.

**Steps**:

1. Create `tests/Unit/SplitCalculatorTest.php`:
   ```php
   <?php

   namespace Tests\Unit;

   use App\Services\SplitCalculator;
   use InvalidArgumentException;
   use PHPUnit\Framework\TestCase;

   class SplitCalculatorTest extends TestCase
   {
       /** @test */
       public function equal_split_divides_evenly(): void
       {
           $result = SplitCalculator::equal(100.00, [1, 2]);
           $this->assertEquals('50.00', $result[1]);
           $this->assertEquals('50.00', $result[2]);
       }

       /** @test */
       public function equal_split_handles_rounding(): void
       {
           $result = SplitCalculator::equal(100.00, [1, 2, 3]);
           // 100/3 = 33.33, last person gets 33.34
           $this->assertEquals('33.33', $result[1]);
           $this->assertEquals('33.33', $result[2]);
           $this->assertEquals('33.34', $result[3]);
           // Total must equal original amount
           $total = bcadd(bcadd($result[1], $result[2], 2), $result[3], 2);
           $this->assertEquals('100.00', $total);
       }

       /** @test */
       public function equal_split_rejects_empty_participants(): void
       {
           $this->expectException(InvalidArgumentException::class);
           SplitCalculator::equal(100.00, []);
       }

       /** @test */
       public function shares_split_calculates_correctly(): void
       {
           $result = SplitCalculator::byShares(100.00, [1 => 2, 2 => 1, 3 => 1]);
           $this->assertEquals('50.00', $result[1]);
           $this->assertEquals('25.00', $result[2]);
           $this->assertEquals('25.00', $result[3]);
       }

       /** @test */
       public function shares_split_handles_rounding(): void
       {
           // 100 split 1:1:1 = same as equal
           $result = SplitCalculator::byShares(100.00, [1 => 1, 2 => 1, 3 => 1]);
           $total = bcadd(bcadd($result[1], $result[2], 2), $result[3], 2);
           $this->assertEquals('100.00', $total);
       }

       /** @test */
       public function percentage_split_calculates_correctly(): void
       {
           $result = SplitCalculator::byPercentage(200.00, [1 => 50, 2 => 30, 3 => 20]);
           $this->assertEquals('100.00', $result[1]);
           $this->assertEquals('60.00', $result[2]);
           $this->assertEquals('40.00', $result[3]);
       }

       /** @test */
       public function percentage_split_rejects_non_100(): void
       {
           $this->expectException(InvalidArgumentException::class);
           SplitCalculator::byPercentage(100.00, [1 => 50, 2 => 30]);
       }

       /** @test */
       public function exact_split_validates_sum(): void
       {
           $result = SplitCalculator::byExact(100.00, [1 => 60, 2 => 40]);
           $this->assertEquals('60.00', $result[1]);
           $this->assertEquals('40.00', $result[2]);
       }

       /** @test */
       public function exact_split_rejects_wrong_sum(): void
       {
           $this->expectException(InvalidArgumentException::class);
           SplitCalculator::byExact(100.00, [1 => 60, 2 => 30]);
       }
   }
   ```

2. Create `tests/Unit/DebtSimplifierTest.php`:
   ```php
   <?php

   namespace Tests\Unit;

   use App\Services\DebtSimplifier;
   use PHPUnit\Framework\TestCase;

   class DebtSimplifierTest extends TestCase
   {
       /** @test */
       public function simplifies_basic_two_person_debt(): void
       {
           // User 1 is owed 50, User 2 owes 50
           $result = DebtSimplifier::simplify([1 => 50, 2 => -50]);
           $this->assertCount(1, $result);
           $this->assertEquals(2, $result[0]['from']);
           $this->assertEquals(1, $result[0]['to']);
           $this->assertEquals(50, $result[0]['amount']);
       }

       /** @test */
       public function simplifies_three_person_debt(): void
       {
           // User 1 is owed 100, User 2 owes 60, User 3 owes 40
           $result = DebtSimplifier::simplify([1 => 100, 2 => -60, 3 => -40]);
           $this->assertCount(2, $result);

           // Check total amounts flow correctly
           $totalToUser1 = collect($result)->where('to', 1)->sum('amount');
           $this->assertEquals(100, $totalToUser1);
       }

       /** @test */
       public function handles_empty_balances(): void
       {
           $result = DebtSimplifier::simplify([]);
           $this->assertEmpty($result);
       }

       /** @test */
       public function handles_already_settled(): void
       {
           $result = DebtSimplifier::simplify([1 => 0, 2 => 0]);
           $this->assertEmpty($result);
       }

       /** @test */
       public function minimizes_transactions(): void
       {
           // 4 people: A owes 30, B owes 20, C is owed 40, D is owed 10
           $result = DebtSimplifier::simplify([
               1 => -30, 2 => -20, 3 => 40, 4 => 10
           ]);

           // Should be 2-3 transactions, not 6 (all pairs)
           $this->assertLessThanOrEqual(3, count($result));

           // Verify net balances are preserved
           $nets = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
           foreach ($result as $txn) {
               $nets[$txn['from']] -= $txn['amount'];
               $nets[$txn['to']] += $txn['amount'];
           }
           $this->assertEqualsWithDelta(-30, $nets[1], 0.01);
           $this->assertEqualsWithDelta(-20, $nets[2], 0.01);
           $this->assertEqualsWithDelta(40, $nets[3], 0.01);
           $this->assertEqualsWithDelta(10, $nets[4], 0.01);
       }
   }
   ```

3. Create `tests/Feature/BalanceCalculatorTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use App\Models\User;
   use App\Models\Expense;
   use App\Models\ExpenseParticipant;
   use App\Models\Settlement;
   use App\Services\BalanceCalculator;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class BalanceCalculatorTest extends TestCase
   {
       use RefreshDatabase;

       /** @test */
       public function calculates_simple_pairwise_balance(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           // Alice pays $100, split equally with Bob
           $expense = Expense::create([
               'description' => 'Dinner',
               'amount' => 100,
               'paid_by' => $alice->id,
               'split_type' => 'equal',
               'expense_date' => now(),
               'created_by' => $alice->id,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $alice->id,
               'owed_amount' => 50,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $bob->id,
               'owed_amount' => 50,
           ]);

           // Bob owes Alice $50
           $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
           $this->assertEquals(50.00, $balance); // positive = Bob owes Alice

           // Alice's perspective: Alice is owed $50 by Bob
           $balance2 = BalanceCalculator::getNetBalance($alice->id, $bob->id);
           $this->assertEquals(-50.00, $balance2); // negative = Alice is owed by Bob
       }

       /** @test */
       public function settlements_reduce_balance(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           $expense = Expense::create([
               'description' => 'Dinner',
               'amount' => 100,
               'paid_by' => $alice->id,
               'split_type' => 'equal',
               'expense_date' => now(),
               'created_by' => $alice->id,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $alice->id,
               'owed_amount' => 50,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $bob->id,
               'owed_amount' => 50,
           ]);

           // Bob pays Alice $30
           Settlement::create([
               'payer_id' => $bob->id,
               'payee_id' => $alice->id,
               'amount' => 30,
               'settled_at' => now(),
           ]);

           // Bob now owes Alice $20
           $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
           $this->assertEquals(20.00, $balance);
       }

       /** @test */
       public function full_settlement_zeroes_balance(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           $expense = Expense::create([
               'description' => 'Dinner',
               'amount' => 100,
               'paid_by' => $alice->id,
               'split_type' => 'equal',
               'expense_date' => now(),
               'created_by' => $alice->id,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $alice->id,
               'owed_amount' => 50,
           ]);

           ExpenseParticipant::create([
               'expense_id' => $expense->id,
               'user_id' => $bob->id,
               'owed_amount' => 50,
           ]);

           Settlement::create([
               'payer_id' => $bob->id,
               'payee_id' => $alice->id,
               'amount' => 50,
               'settled_at' => now(),
           ]);

           $balance = BalanceCalculator::getNetBalance($bob->id, $alice->id);
           $this->assertEquals(0.00, $balance);
       }
   }
   ```

4. Create `tests/Feature/ExpenseFlowTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use App\Models\User;
   use App\Models\Friendship;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class ExpenseFlowTest extends TestCase
   {
       use RefreshDatabase;

       /** @test */
       public function authenticated_user_can_create_expense(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();
           Friendship::create([
               'requester_id' => $alice->id,
               'addressee_id' => $bob->id,
               'status' => 'accepted',
           ]);

           $response = $this->actingAs($alice)->post('/expenses', [
               'description' => 'Lunch',
               'amount' => 50,
               'expense_date' => '2026-08-06',
               'group_id' => null,
               'paid_by' => $alice->id,
               'split_type' => 'equal',
               'participants' => [
                   ['user_id' => $alice->id, 'share_value' => null, 'amount' => null],
                   ['user_id' => $bob->id, 'share_value' => null, 'amount' => null],
               ],
           ]);

           $response->assertRedirect();
           $this->assertDatabaseHas('expenses', [
               'description' => 'Lunch',
               'amount' => 50,
               'paid_by' => $alice->id,
           ]);
           $this->assertDatabaseHas('expense_participants', [
               'user_id' => $bob->id,
               'owed_amount' => 25,
           ]);
       }

       /** @test */
       public function unauthenticated_user_cannot_create_expense(): void
       {
           $response = $this->post('/expenses', []);
           $response->assertRedirect('/login');
       }
   }
   ```

5. Create `tests/Feature/CircleTest.php`:
   ```php
   <?php

   namespace Tests\Feature;

   use App\Models\User;
   use App\Models\Friendship;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   class CircleTest extends TestCase
   {
       use RefreshDatabase;

       /** @test */
       public function user_can_send_friend_request(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           $response = $this->actingAs($alice)->post("/circle/invite/{$bob->id}");

           $response->assertRedirect();
           $this->assertDatabaseHas('friendships', [
               'requester_id' => $alice->id,
               'addressee_id' => $bob->id,
               'status' => 'pending',
           ]);
       }

       /** @test */
       public function user_can_accept_friend_request(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           $friendship = Friendship::create([
               'requester_id' => $alice->id,
               'addressee_id' => $bob->id,
               'status' => 'pending',
           ]);

           $response = $this->actingAs($bob)->post("/circle/accept/{$friendship->id}");

           $response->assertRedirect();
           $this->assertDatabaseHas('friendships', [
               'id' => $friendship->id,
               'status' => 'accepted',
           ]);
       }

       /** @test */
       public function user_cannot_accept_others_request(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();
           $charlie = User::factory()->create();

           $friendship = Friendship::create([
               'requester_id' => $alice->id,
               'addressee_id' => $bob->id,
               'status' => 'pending',
           ]);

           // Charlie tries to accept Alice->Bob request
           $response = $this->actingAs($charlie)->post("/circle/accept/{$friendship->id}");
           $response->assertForbidden();
       }

       /** @test */
       public function cannot_send_duplicate_friend_request(): void
       {
           $alice = User::factory()->create();
           $bob = User::factory()->create();

           Friendship::create([
               'requester_id' => $alice->id,
               'addressee_id' => $bob->id,
               'status' => 'pending',
           ]);

           $response = $this->actingAs($alice)->post("/circle/invite/{$bob->id}");
           $response->assertSessionHasErrors();
       }
   }
   ```

6. Run all tests:
   ```bash
   php artisan test
   ```

**Verification**:
- All tests pass
- No failures or errors

**Update PROGRESS.md**: Mark Phase 12 complete.

---

## 9. Post-Completion Checklist

After all phases are done, verify:

- [ ] User can register with username
- [ ] User can log in
- [ ] User can search and add friends
- [ ] User can accept/reject friend requests
- [ ] User can create a group with friends
- [ ] User can add an expense (all 4 split types)
- [ ] Dashboard shows correct balances
- [ ] User can record a settlement
- [ ] Balances update after settlement
- [ ] Group detail shows simplified debts
- [ ] Activity feed shows all transactions
- [ ] Mobile layout works (bottom nav, responsive)
- [ ] Desktop layout works (sidebar, centered content)
- [ ] All PHPUnit tests pass
- [ ] No console errors in browser

---

## 10. Database MCP Configuration

For Cursor to have direct database access, configure the MySQL MCP server.

### Option A: Use `@benborla29/mcp-server-mysql`

Add to your Cursor MCP settings (`.cursor/mcp.json` or Cursor settings):

```json
{
  "mcpServers": {
    "mysql": {
      "command": "npx",
      "args": ["-y", "@benborla29/mcp-server-mysql"],
      "env": {
        "MYSQL_HOST": "127.0.0.1",
        "MYSQL_PORT": "3306",
        "MYSQL_USER": "root",
        "MYSQL_PASSWORD": "",
        "MYSQL_DATABASE": "spliteven"
      }
    }
  }
}
```

### Option B: Use `@modelcontextprotocol/server-postgres` (if using PostgreSQL)

Not applicable here, but noted for reference.

### Verification

After configuring, the agent should be able to:
- List tables
- Describe table schemas
- Run SELECT queries to verify data

---

## 11. Tips for the AI Agent

1. **Don't modify Service classes**: The code in `SplitCalculator`, `BalanceCalculator`, and `DebtSimplifier` is pre-tested. Copy it exactly.

2. **Use Inertia forms**: For form submissions, use Inertia's `useForm` helper:
   ```javascript
   import { useForm } from '@inertiajs/vue3';
   const form = useForm({ ... });
   form.post(route('expenses.store'));
   ```

3. **Route helper**: Use the `route()` helper from Ziggy (included with Inertia):
   ```javascript
   import { route } from 'ziggy-js';
   // or it may already be available globally
   ```

4. **Flash messages**: Use Inertia shared data for flash messages:
   ```php
   // In HandleInertiaRequests middleware:
   'flash' => [
       'success' => fn () => $request->session()->get('success'),
       'error' => fn () => $request->session()->get('error'),
   ],
   ```

5. **Imports matter**: Always check that all used classes are imported at the top of PHP files.

6. **bcmath extension**: Ensure PHP has the `bcmath` extension enabled (it usually is by default).

7. **One thing at a time**: Create one file, verify it works (no syntax errors), then move to the next.

8. **When stuck**: Re-read the relevant section of this plan. The answer is here.

---

## 12. File Tree (Expected Final Structure)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── ActivityController.php
│   │   ├── CircleController.php
│   │   ├── DashboardController.php
│   │   ├── ExpenseController.php
│   │   ├── GroupController.php
│   │   └── SettlementController.php
│   └── Requests/
│       └── StoreExpenseRequest.php
├── Models/
│   ├── Expense.php
│   ├── ExpenseParticipant.php
│   ├── Friendship.php
│   ├── Group.php
│   ├── Settlement.php
│   └── User.php
└── Services/
    ├── BalanceCalculator.php
    ├── DebtSimplifier.php
    └── SplitCalculator.php

resources/js/
├── Components/
│   ├── AmountDisplay.vue
│   ├── BalanceCard.vue
│   ├── BottomNav.vue
│   ├── EmptyState.vue
│   ├── ExpenseListItem.vue
│   ├── SearchSheet.vue
│   ├── SplitForm.vue
│   ├── UserAvatar.vue
│   └── ui/           ← shadcn-vue components (auto-generated)
├── Layouts/
│   └── AppLayout.vue
└── Pages/
    ├── Activity/
    │   └── Index.vue
    ├── Auth/
    │   ├── Login.vue   ← (exists from starter kit)
    │   └── Register.vue ← (modify to add username)
    ├── Circle/
    │   └── Index.vue
    ├── Dashboard.vue
    ├── Expenses/
    │   ├── Create.vue
    │   └── Show.vue
    ├── Groups/
    │   ├── Create.vue
    │   ├── Index.vue
    │   └── Show.vue
    └── Settlements/
        └── Create.vue

database/migrations/
├── ...existing...
├── xxxx_add_username_to_users_table.php
├── xxxx_create_friendships_table.php
├── xxxx_create_groups_table.php
├── xxxx_create_group_members_table.php
├── xxxx_create_expenses_table.php
├── xxxx_create_expense_participants_table.php
└── xxxx_create_settlements_table.php

tests/
├── Feature/
│   ├── BalanceCalculatorTest.php
│   ├── CircleTest.php
│   └── ExpenseFlowTest.php
└── Unit/
    ├── DebtSimplifierTest.php
    └── SplitCalculatorTest.php
```
