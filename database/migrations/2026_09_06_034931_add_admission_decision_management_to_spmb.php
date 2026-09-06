<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->json('re_registration_requirements')->nullable()->after('test_definitions');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->timestamp('re_registration_completed_at')->nullable()->after('accepted_at');
            $table->timestamp('enrolled_at')->nullable()->after('re_registration_completed_at');
            $table->foreignId('enrolled_by')->nullable()->after('enrolled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('selections', function (Blueprint $table): void {
            $table->foreignId('selection_batch_id')->nullable()->after('registration_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('rank')->nullable()->after('final_score');
            $table->unsignedInteger('waitlist_rank')->nullable()->after('rank');
            $table->string('system_recommendation', 20)->nullable()->after('waitlist_rank');
            $table->text('override_reason')->nullable()->after('notes');
            $table->index(['selection_batch_id', 'rank']);
            $table->index(['decision', 'waitlist_rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selections', function (Blueprint $table): void {
            $table->dropIndex(['selection_batch_id', 'rank']);
            $table->dropIndex(['decision', 'waitlist_rank']);
            $table->dropConstrainedForeignId('selection_batch_id');
            $table->dropColumn(['rank', 'waitlist_rank', 'system_recommendation', 'override_reason']);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enrolled_by');
            $table->dropColumn(['re_registration_completed_at', 'enrolled_at']);
        });

        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn('re_registration_requirements');
        });
    }
};
