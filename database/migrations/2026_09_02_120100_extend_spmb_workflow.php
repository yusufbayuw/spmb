<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('registrant_type', 20)->default('self')->after('unit_id');
            $table->string('registrant_relationship', 30)->nullable()->after('registrant_type');
            $table->string('current_stage', 40)->default('data_validation')->after('status');
            $table->string('data_validation_status', 20)->default('pending')->after('current_stage');
            $table->text('data_validation_notes')->nullable()->after('data_validation_status');
            $table->foreignId('data_validated_by')->nullable()->after('data_validation_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('data_validated_at')->nullable()->after('data_validated_by');
            $table->string('applicant_card_number', 80)->nullable()->unique()->after('data_validated_at');
            $table->foreignId('applicant_card_issued_by')->nullable()->after('applicant_card_number')->constrained('users')->nullOnDelete();
            $table->timestamp('applicant_card_issued_at')->nullable()->after('applicant_card_issued_by');
            $table->timestamp('documents_completed_at')->nullable()->after('applicant_card_issued_at');
            $table->timestamp('documents_verified_at')->nullable()->after('documents_completed_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('payment_method');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_original_name');
            $table->text('rejection_reason')->nullable()->after('note');
            $table->timestamp('va_sent_at')->nullable()->after('verified_at');
            $table->foreignId('va_sent_by')->nullable()->after('va_sent_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('admission_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->decimal('passing_score', 8, 2)->nullable();
            $table->string('result_type', 20)->default('score');
            $table->timestamps();
            $table->unique(['unit_id', 'code']);
        });

        Schema::create('admission_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_test_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->decimal('score', 8, 2)->nullable();
            $table->string('result', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'admission_test_id']);
        });

        Schema::create('selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('decision', 20)->default('pending');
            $table->decimal('final_score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('selections');
        Schema::dropIfExists('admission_test_results');
        Schema::dropIfExists('admission_tests');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('va_sent_by');
            $table->dropColumn(['proof_path','proof_original_name','proof_uploaded_at','rejection_reason','va_sent_at']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_validated_by');
            $table->dropConstrainedForeignId('applicant_card_issued_by');
            $table->dropColumn(['registrant_type','registrant_relationship','current_stage','data_validation_status','data_validation_notes','data_validated_at','applicant_card_number','applicant_card_issued_at','documents_completed_at','documents_verified_at']);
        });
    }
};
