<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            if (! Schema::hasColumn('units', 'public_headline')) {
                $table->string('public_headline', 180)->nullable()->after('description');
            }

            if (! Schema::hasColumn('units', 'public_body')) {
                $table->longText('public_body')->nullable()->after('public_headline');
            }
        });

        Schema::table('study_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('study_programs', 'public_headline')) {
                $table->string('public_headline', 180)->nullable()->after('description');
            }

            if (! Schema::hasColumn('study_programs', 'public_body')) {
                $table->longText('public_body')->nullable()->after('public_headline');
            }

            if (! Schema::hasColumn('study_programs', 'study_duration')) {
                $table->string('study_duration', 100)->nullable()->after('public_body');
            }

            if (! Schema::hasColumn('study_programs', 'public_highlights')) {
                $table->json('public_highlights')->nullable()->after('study_duration');
            }

            if (! Schema::hasColumn('study_programs', 'public_target_audiences')) {
                $table->json('public_target_audiences')->nullable()->after('public_highlights');
            }
        });

        if (! Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('unit_id')->constrained()->restrictOnDelete();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->foreignId('registration_pathway_id')->nullable()->constrained('registration_pathways')->nullOnDelete();
                $table->string('question', 255);
                $table->longText('answer');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['unit_id', 'is_active', 'sort_order'], 'faqs_unit_active_sort');
                $table->index(['study_program_id', 'registration_pathway_id'], 'faqs_public_context');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');

        Schema::table('study_programs', function (Blueprint $table): void {
            foreach (['public_target_audiences', 'public_highlights', 'study_duration', 'public_body', 'public_headline'] as $column) {
                if (Schema::hasColumn('study_programs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('units', function (Blueprint $table): void {
            foreach (['public_body', 'public_headline'] as $column) {
                if (Schema::hasColumn('units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
