<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('work_date');
            $table->string('type', 10);
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status', 10)->default('approved')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'approval_status']);
        });

        Schema::create('salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('monthly_salary_paise');
            $table->unsignedBigInteger('earned_paise');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'month']);
        });

        Schema::create('salary_advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('month');
            $table->date('paid_on');
            $table->unsignedBigInteger('amount_paise');
            $table->string('method', 12);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'month']);
        });

        Schema::create('salary_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_id')->constrained('salaries')->restrictOnDelete();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount_paise');
            $table->string('method', 12);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('attendances');
    }
};
