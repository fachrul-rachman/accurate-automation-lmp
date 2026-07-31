<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->string('delivery_note_number', 100)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->dropColumn('delivery_note_number');
        });
    }
};
