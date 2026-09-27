<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entry_items', function (Blueprint $table): void {
            $table->string('type', 12)->nullable()->index();
        });
        // Preserve the type recorded by older versions for every existing row.
        DB::statement('UPDATE stock_entry_items SET type = (SELECT stock_entries.type FROM stock_entries WHERE stock_entries.id = stock_entry_items.stock_entry_id)');
        Schema::table('stock_entries', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('stock_entry_items', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
