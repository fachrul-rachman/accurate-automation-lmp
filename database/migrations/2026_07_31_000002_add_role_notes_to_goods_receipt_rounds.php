<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->text('head_warehouse_notes')->nullable()->after('head_warehouse_signed_at');
            $table->text('user_notes')->nullable()->after('user_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->dropColumn(['head_warehouse_notes', 'user_notes']);
        });
    }
};
