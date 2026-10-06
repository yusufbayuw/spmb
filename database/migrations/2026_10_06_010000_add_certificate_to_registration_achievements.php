<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_achievements', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('certificate_path', 1000)->nullable()->after('description');
            $table->string('certificate_original_name')->nullable()->after('certificate_path');
        });

        DB::table('registration_achievements')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('registration_achievements')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('registration_achievements', function (Blueprint $table): void {
            $table->unique('uuid', 'registration_achievements_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('registration_achievements', function (Blueprint $table): void {
            $table->dropUnique('registration_achievements_uuid_unique');
            $table->dropColumn(['uuid', 'certificate_path', 'certificate_original_name']);
        });
    }
};
