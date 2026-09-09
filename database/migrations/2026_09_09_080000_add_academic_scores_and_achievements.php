<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->boolean('academic_scores_enabled')->default(false)->after('post_announcement_enabled');
            $table->json('academic_score_settings')->nullable()->after('academic_scores_enabled');
            $table->boolean('achievements_enabled')->default(false)->after('academic_score_settings');
            $table->json('achievement_settings')->nullable()->after('achievements_enabled');
        });

        Schema::create('registration_academic_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('grade_key', 60);
            $table->string('subject_key', 60);
            $table->string('assessment_key', 60);
            $table->decimal('score', 8, 2);
            $table->timestamps();

            $table->unique(
                ['registration_id', 'grade_key', 'subject_key', 'assessment_key'],
                'registration_academic_scores_unique'
            );
            $table->index(['registration_id', 'subject_key'], 'registration_academic_scores_lookup');
        });

        Schema::create('registration_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('level', 100);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('organizer', 200)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'level'], 'registration_achievements_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_achievements');
        Schema::dropIfExists('registration_academic_scores');

        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'academic_scores_enabled',
                'academic_score_settings',
                'achievements_enabled',
                'achievement_settings',
            ]);
        });
    }
};
