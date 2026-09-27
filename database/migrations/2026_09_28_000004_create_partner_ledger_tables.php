<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('key', 150)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_account_id')->constrained('partner_accounts')->restrictOnDelete();
            $table->date('transaction_date')->index();
            $table->string('type', 7); // send / receive
            $table->unsignedBigInteger('amount_rupees');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['partner_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
        Schema::dropIfExists('partner_accounts');
    }
};
