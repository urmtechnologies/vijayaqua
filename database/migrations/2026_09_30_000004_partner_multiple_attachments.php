<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('partner_transactions', 'attachment_paths')) {
            Schema::table('partner_transactions', fn (Blueprint $table) => $table->json('attachment_paths')->nullable());
        }

        if (Schema::hasColumn('partner_transactions', 'attachment_path')) {
            // Include soft-deleted transactions and preserve files already uploaded.
            DB::table('partner_transactions')->select('id', 'attachment_path', 'attachment_paths')
                ->orderBy('id')->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $paths = json_decode($row->attachment_paths ?? '[]', true) ?: [];
                        if ($row->attachment_path) $paths[] = $row->attachment_path;
                        DB::table('partner_transactions')->where('id', $row->id)
                            ->update(['attachment_paths' => json_encode(array_values(array_unique($paths)), JSON_THROW_ON_ERROR)]);
                    }
                });
            Schema::table('partner_transactions', fn (Blueprint $table) => $table->dropColumn('attachment_path'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('partner_transactions', 'attachment_path')) {
            Schema::table('partner_transactions', fn (Blueprint $table) => $table->string('attachment_path')->nullable());
        }
        DB::table('partner_transactions')->select('id', 'attachment_paths')->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $paths = json_decode($row->attachment_paths ?? '[]', true) ?: [];
                    DB::table('partner_transactions')->where('id', $row->id)->update(['attachment_path' => $paths[0] ?? null]);
                }
            });
        // Retain the JSON archive on rollback so additional image paths are not lost.
    }
};
