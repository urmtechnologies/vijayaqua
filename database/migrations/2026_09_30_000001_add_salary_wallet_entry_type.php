<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            // Existing advances were money paid to staff.
            $table->string('entry_type', 6)->default('credit')->after('amount_paise');
        });
    }

    public function down(): void
    {
        Schema::table('salary_advances', fn (Blueprint $table) => $table->dropColumn('entry_type'));
    }
};
