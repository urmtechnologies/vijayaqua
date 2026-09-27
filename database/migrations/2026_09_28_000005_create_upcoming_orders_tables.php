<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcoming_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('scheduled_date')->index();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'scheduled_date']);
        });

        Schema::create('upcoming_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('upcoming_order_id')->constrained('upcoming_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->unsignedBigInteger('cartons');
            $table->timestamps();
            $table->unique(['upcoming_order_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcoming_order_items');
        Schema::dropIfExists('upcoming_orders');
    }
};
