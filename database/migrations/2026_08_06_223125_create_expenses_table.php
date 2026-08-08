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
