<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'HOD')->update(['role' => 'USER']);

        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->renameColumn('selected_hod_id', 'selected_user_id');
            $table->renameColumn('hod_signed_by', 'user_signed_by');
            $table->renameColumn('hod_signed_at', 'user_signed_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        DB::table('goods_receipt_rounds')
            ->where('round_status', 'WAITING_HOD_SIGNATURE')
            ->update(['round_status' => 'WAITING_HEAD_WAREHOUSE_SIGNATURE']);

        DB::table('goods_receipt_rounds')
            ->where('round_status', 'WAITING_HEAD_WAREHOUSE_SIGNATURE')
            ->whereNotNull('user_signed_at')
            ->update([
                'round_status' => 'NEEDS_REVISION',
                'user_signed_by' => null,
                'user_signed_at' => null,
                'rejection_reason' => 'Alur tanda tangan berubah. Warehouse perlu mengirim ulang pemeriksaan.',
            ]);
    }

    public function down(): void
    {
        DB::table('goods_receipt_rounds')
            ->whereIn('round_status', ['WAITING_USER_SIGNATURE', 'NEEDS_REVISION'])
            ->update(['round_status' => 'WAITING_HOD_SIGNATURE']);

        Schema::table('goods_receipt_rounds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
            $table->renameColumn('selected_user_id', 'selected_hod_id');
            $table->renameColumn('user_signed_by', 'hod_signed_by');
            $table->renameColumn('user_signed_at', 'hod_signed_at');
        });

        DB::table('users')->where('role', 'USER')->update(['role' => 'HOD']);
    }
};
