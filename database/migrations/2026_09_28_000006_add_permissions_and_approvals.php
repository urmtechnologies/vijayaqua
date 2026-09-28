<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('scope', 4)->default('self');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_invoice')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'module']);
        });

        foreach (['users', 'products', 'stock_entries', 'sales', 'sale_payments', 'expenses', 'partner_transactions', 'upcoming_orders'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('approval_status', 10)->default('approved')->index();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
            });
        }
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['updated_by', 'deleted_at']);
        });
        foreach (['users', 'products', 'stock_entries', 'sales', 'sale_payments', 'expenses', 'partner_transactions', 'upcoming_orders'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['approved_by']);
                $table->dropColumn(['approval_status', 'approved_by', 'approved_at']);
            });
        }
        Schema::dropIfExists('user_permissions');
    }
};
