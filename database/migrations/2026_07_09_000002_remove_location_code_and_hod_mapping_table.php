<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hod_location_mappings');

        if (Schema::hasColumn('locations', 'code')) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->dropColumn('code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('locations', 'code')) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->string('code')->nullable()->unique()->after('name');
            });
        }

        Schema::create('hod_location_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hod_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['location_id', 'hod_user_id']);
        });
    }
};
