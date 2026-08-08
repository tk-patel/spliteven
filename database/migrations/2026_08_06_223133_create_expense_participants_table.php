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
