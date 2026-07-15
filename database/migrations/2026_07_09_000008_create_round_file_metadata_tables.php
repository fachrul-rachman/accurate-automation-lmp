<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_round_item_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_item_id')->constrained('goods_receipt_round_items')->cascadeOnDelete();
            $table->string('photo_type', 20);
            $table->string('r2_key');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::create('round_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained('goods_receipt_rounds')->cascadeOnDelete();
            $table->string('attachment_type', 30);
            $table->string('r2_key');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_attachments');
        Schema::dropIfExists('goods_receipt_round_item_photos');
    }
};
