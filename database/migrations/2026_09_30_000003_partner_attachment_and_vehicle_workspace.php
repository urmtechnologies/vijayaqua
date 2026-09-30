<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('partner_transactions', 'attachment_path')) {
            Schema::table('partner_transactions', fn (Blueprint $table) => $table->string('attachment_path')->nullable());
        }
        Schema::create('vehicle_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reference_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_destination', 150);
            $table->string('to_destination', 150);
            $table->date('entry_date')->index();
            $table->decimal('amount_rupees', 18, 2);
            $table->string('approval_status', 10)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['reference_user_id', 'entry_date']);
        });
        Schema::create('vehicle_entry_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_entry_id')->constrained('vehicle_entries')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->unsignedInteger('cartons');
            $table->timestamps();
            $table->unique(['vehicle_entry_id', 'product_id']);
        });
        Schema::create('vehicle_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reference_user_id')->constrained('users')->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->string('entry_type', 6); // credit = paid out, debit = payment returned
            $table->string('method', 20);
            $table->decimal('amount_rupees', 18, 2);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['reference_user_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_payments');
        Schema::dropIfExists('vehicle_entry_items');
        Schema::dropIfExists('vehicle_entries');
        Schema::table('partner_transactions', fn (Blueprint $table) => $table->dropColumn('attachment_path'));
    }
};
