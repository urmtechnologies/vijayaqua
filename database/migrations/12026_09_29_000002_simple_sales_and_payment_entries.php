<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'business_name')) $table->string('business_name', 150)->nullable();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('sale_items', 'discount_rupees')) $table->decimal('discount_rupees', 18, 2)->default(0);
            if (! Schema::hasColumn('sale_items', 'reference_user_id')) $table->foreignId('reference_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('sale_payments', function (Blueprint $table): void {
            // Existing payments were all receipts.
            if (! Schema::hasColumn('sale_payments', 'entry_type')) $table->string('entry_type', 6)->default('credit');
        });

        // Keep the old invoice-wide reference visible on its existing product lines.
        foreach (DB::table('sales')->whereNotNull('reference_user_id')->select('id', 'reference_user_id')->cursor() as $sale) {
            DB::table('sale_items')->where('sale_id', $sale->id)->whereNull('reference_user_id')
                ->update(['reference_user_id' => $sale->reference_user_id]);
        }
        foreach (DB::table('customers')->pluck('id') as $id) {
            \App\Support\CustomerWallet::refresh((int) $id);
        }
    }

    public function down(): void
    {
        // Columns may have existed before this migration was recorded. Preserve
        // them on rollback so existing party and payment records remain intact.
    }
};
