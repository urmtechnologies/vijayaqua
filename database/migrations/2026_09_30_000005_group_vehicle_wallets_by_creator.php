<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicle_payments', 'account_user_id')) {
            Schema::table('vehicle_payments', function (Blueprint $table): void {
                $table->foreignId('account_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->index(['account_user_id', 'payment_date']);
            });
        }

        // A payment now belongs to the person who added the trips. Keep its old reference for audit.
        DB::table('vehicle_payments')->whereNull('account_user_id')->orderBy('id')->chunkById(100, function ($payments): void {
            foreach ($payments as $payment) {
                $trips = DB::table('vehicle_entries')->where('reference_user_id', $payment->reference_user_id);
                $creators = (clone $trips)->where('approval_status', 'approved')->distinct()->pluck('user_id');
                if ($creators->isEmpty()) $creators = $trips->distinct()->pluck('user_id');
                if ($creators->count() !== 1) {
                    throw new RuntimeException('Vehicle payment #'.$payment->id.' needs its added-by user. Set vehicle_payments.account_user_id for this payment and run migrate again. The existing payment has been preserved.');
                }
                DB::table('vehicle_payments')->where('id', $payment->id)->whereNull('account_user_id')
                    ->update(['account_user_id' => $creators->first()]);
            }
        });

        Schema::table('vehicle_payments', fn (Blueprint $table) => $table->foreignId('reference_user_id')->nullable()->change());
    }

    public function down(): void
    {
        // Preserve ownership and history: a combined payment cannot safely be split back between references.
    }
};
