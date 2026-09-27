<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'products', 'stock_entries'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('stock_entries', function (Blueprint $table): void {
            // Old entries have no recorded type; keep that history honest.
            $table->string('type', 12)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table): void {
            $table->dropColumn('type');
        });

        foreach (['stock_entries', 'products', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
