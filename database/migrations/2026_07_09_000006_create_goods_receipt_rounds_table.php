<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('goods_receipt_inspections')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('selected_hod_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('round_status', 40);
            $table->string('completion_result', 40)->nullable();
            $table->boolean('specification_matches_po')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('hod_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hod_signed_at')->nullable();
            $table->foreignId('head_warehouse_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('head_warehouse_signed_at')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_rounds');
    }
};
