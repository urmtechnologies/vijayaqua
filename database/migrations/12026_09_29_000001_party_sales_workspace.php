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
            if (! Schema::hasColumn('sales', 'is_draft')) $table->boolean('is_draft')->default(false)->index();
            if (! Schema::hasColumn('sales', 'reference_user_id')) $table->foreignId('reference_user_id')->nullable()->constrained('users')->nullOnDelete();
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
            if (! Schema::hasColumn('customers', 'total_sales_rupees')) $table->decimal('total_sales_rupees', 18, 2)->default(0);
            if (! Schema::hasColumn('customers', 'total_paid_rupees')) $table->decimal('total_paid_rupees', 18, 2)->default(0);
            if (! Schema::hasColumn('customers', 'balance_rupees')) $table->decimal('balance_rupees', 18, 2)->default(0);
        });

        // Entry type is added by the next migration; it refreshes all wallets.
        if (Schema::hasColumn('sale_payments', 'entry_type')) {
            foreach (DB::table('customers')->pluck('id') as $id) {
                \App\Support\CustomerWallet::refresh((int) $id);
            }
        }
    }

    public function down(): void
    {
        // An interrupted migration can leave pre-existing columns behind. A rollback
        // cannot tell which columns it created, so preserve the data and schema.
    }
};
