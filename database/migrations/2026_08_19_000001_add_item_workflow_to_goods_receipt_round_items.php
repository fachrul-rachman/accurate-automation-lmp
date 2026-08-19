<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_round_items', function (Blueprint $table): void {
            $table->foreignId('selected_user_id')->nullable()->after('round_id')->constrained('users')->nullOnDelete();
            $table->string('head_status', 20)->default('PENDING')->after('damaged_qty');
            $table->foreignId('head_signed_by')->nullable()->after('head_status')->constrained('users')->nullOnDelete();
            $table->timestamp('head_signed_at')->nullable()->after('head_signed_by');
            $table->string('user_status', 20)->default('PENDING')->after('head_signed_at');
            $table->foreignId('user_signed_by')->nullable()->after('user_status')->constrained('users')->nullOnDelete();
            $table->timestamp('user_signed_at')->nullable()->after('user_signed_by');
            $table->text('rejection_reason')->nullable()->after('user_signed_at');
        });

        DB::table('goods_receipt_rounds')->orderBy('id')->each(function (object $round): void {
            $headStatus = match ($round->round_status) {
                'WAITING_USER_SIGNATURE', 'SIGNED' => 'APPROVED',
                'NEEDS_REVISION' => 'REJECTED',
                default => 'PENDING',
            };
            $userStatus = $round->round_status === 'SIGNED' ? 'SIGNED' : 'PENDING';

            DB::table('goods_receipt_round_items')->where('round_id', $round->id)->update([
                'selected_user_id' => $round->selected_user_id,
                'head_status' => $headStatus,
                'head_signed_by' => $headStatus === 'APPROVED' ? $round->head_warehouse_signed_by : null,
                'head_signed_at' => $headStatus === 'APPROVED' ? $round->head_warehouse_signed_at : null,
                'user_status' => $userStatus,
                'user_signed_by' => $userStatus === 'SIGNED' ? $round->user_signed_by : null,
                'user_signed_at' => $userStatus === 'SIGNED' ? $round->user_signed_at : null,
                'rejection_reason' => $round->round_status === 'NEEDS_REVISION' ? $round->rejection_reason : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_round_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_user_id');
            $table->dropConstrainedForeignId('head_signed_by');
            $table->dropConstrainedForeignId('user_signed_by');
            $table->dropColumn(['head_status', 'head_signed_at', 'user_status', 'user_signed_at', 'rejection_reason']);
        });
    }
};
