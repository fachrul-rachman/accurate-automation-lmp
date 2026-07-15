<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('goods_receipt_inspections')->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('r2_key');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_exports');
    }
};
