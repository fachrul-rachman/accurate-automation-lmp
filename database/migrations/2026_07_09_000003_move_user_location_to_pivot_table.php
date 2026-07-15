<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('location_user')) {
            Schema::create('location_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['location_id', 'user_id']);
            });
        }

        if (Schema::hasColumn('users', 'location_id')) {
            $now = now();
            $users = DB::table('users')
                ->whereNotNull('location_id')
                ->get(['id', 'location_id']);

            foreach ($users as $user) {
                DB::table('location_user')->updateOrInsert(
                    [
                        'location_id' => $user->location_id,
                        'user_id' => $user->id,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('location_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'location_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('locations')
                    ->nullOnDelete();
            });
        }

        $pairs = DB::table('location_user')
            ->orderBy('id')
            ->get(['user_id', 'location_id'])
            ->unique('user_id');

        foreach ($pairs as $pair) {
            DB::table('users')
                ->where('id', $pair->user_id)
                ->update(['location_id' => $pair->location_id]);
        }

        Schema::dropIfExists('location_user');
    }
};
