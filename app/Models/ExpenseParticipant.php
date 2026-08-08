<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'user_id',
        'share_value',
        'owed_amount',
    ];

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
