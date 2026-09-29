<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing whole-rupee amounts remain the same numbers after conversion.
        Schema::table('sales', function (Blueprint $table): void {
            $table->boolean('is_draft')->default(false)->index();
            $table->foreignId('reference_user_id')->nullable()->constrained('users')->nullOnDelete();
            foreach (['subtotal_rupees', 'discount_rupees', 'vehicle_charge_rupees', 'total_rupees'] as $column) {
                $table->decimal($column, 18, 2)->change();
            }
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('rate_rupees', 18, 2)->change();
            $table->decimal('line_total_rupees', 18, 2)->change();
        });
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->decimal('amount_rupees', 18, 2)->change();
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->decimal('total_sales_rupees', 18, 2)->default(0);
            $table->decimal('total_paid_rupees', 18, 2)->default(0);
            $table->decimal('balance_rupees', 18, 2)->default(0);
        });

        // Backfill the wallet cache from the existing approved invoice/payment ledger.
        foreach (DB::table('customers')->pluck('id') as $id) {
            \App\Support\CustomerWallet::refresh((int) $id);
        }
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn([
            'total_sales_rupees', 'total_paid_rupees', 'balance_rupees',
        ]));
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reference_user_id');
            $table->dropColumn('is_draft');
        });
        // Keep decimal columns: rolling back them to integers would discard paise.
    }
};
