<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('key', 80)->unique();
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('title', 150);
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->unsignedBigInteger('amount_rupees');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['expense_category_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
