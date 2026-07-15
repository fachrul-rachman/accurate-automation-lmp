<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_round_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained('goods_receipt_rounds')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('accurate_detail_item_id')->nullable();
            $table->string('accurate_item_id')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name');
            $table->decimal('qty', 15, 4);
            $table->string('unit')->nullable();
            $table->string('po_number')->nullable();
            $table->string('quality_status', 20);
            $table->string('quantity_status', 20);
            $table->decimal('received_qty', 15, 4)->nullable();
            $table->decimal('damaged_qty', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_round_items');
    }
};
