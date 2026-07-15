<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('accurate_receive_item_id')->nullable();
            $table->string('pb_number')->unique();
            $table->string('po_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->date('pb_date')->nullable();
            $table->json('accurate_raw_json');
            $table->string('pb_status', 32);
            $table->string('pdf_status', 32)->default('NOT_READY');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_inspections');
    }
};
