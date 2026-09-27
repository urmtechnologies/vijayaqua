<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('mobile', 10)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_no', 32)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('sale_date')->index();
            $table->unsignedBigInteger('subtotal_rupees');
            $table->unsignedBigInteger('discount_rupees')->default(0);
            $table->unsignedBigInteger('vehicle_charge_rupees')->default(0);
            $table->unsignedBigInteger('total_rupees');
            $table->date('due_date')->nullable();
            $table->string('reference', 150)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->unsignedBigInteger('cartons');
            $table->unsignedBigInteger('rate_rupees');
            $table->unsignedBigInteger('line_total_rupees');
            $table->timestamps();
            $table->unique(['sale_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->unsignedBigInteger('amount_rupees');
            $table->string('method', 12);
            $table->string('reference', 150)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
