<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing Vijay Aqua login already added users.mobile and users.role.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->decimal('salary', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('salary');
            // Do not make email required again: staff records have no email.
        });
    }
};
