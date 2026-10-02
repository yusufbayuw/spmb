<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table): void {
            $table->string('seed_key', 120)->nullable()->after('training_program_id');
            $table->unique(['training_program_id', 'seed_key'], 'training_modules_program_seed_unique');
        });

        Schema::table('training_lessons', function (Blueprint $table): void {
            $table->string('seed_key', 140)->nullable()->after('training_module_id');
            $table->unique(['training_module_id', 'seed_key'], 'training_lessons_module_seed_unique');
        });

        Schema::table('certification_questions', function (Blueprint $table): void {
            $table->string('seed_key', 140)->nullable()->after('certification_program_id');
            $table->unique(['certification_program_id', 'seed_key'], 'cert_questions_program_seed_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certification_questions', function (Blueprint $table): void {
            $table->dropUnique('cert_questions_program_seed_unique');
            $table->dropColumn('seed_key');
        });

        Schema::table('training_lessons', function (Blueprint $table): void {
            $table->dropUnique('training_lessons_module_seed_unique');
            $table->dropColumn('seed_key');
        });

        Schema::table('training_modules', function (Blueprint $table): void {
            $table->dropUnique('training_modules_program_seed_unique');
            $table->dropColumn('seed_key');
        });
    }
};
