<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->decimal('registration_fee', 15, 2)->default(0)->after('pathway');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('lifecycle_status', 20)->default('active')->after('current_stage');
            $table->text('lifecycle_reason')->nullable()->after('lifecycle_status');
            $table->foreignId('lifecycle_changed_by')->nullable()->after('lifecycle_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('lifecycle_changed_at')->nullable()->after('lifecycle_changed_by');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('mime_type', 120)->nullable()->after('file_type');
            $table->string('sha256', 64)->nullable()->after('file_size');
            $table->string('malware_scan_status', 30)->default('not_scanned')->after('sha256');
            $table->timestamp('security_scanned_at')->nullable()->after('malware_scan_status');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('proof_mime_type', 120)->nullable()->after('proof_original_name');
            $table->string('proof_sha256', 64)->nullable()->after('proof_mime_type');
            $table->string('proof_malware_scan_status', 30)->default('not_scanned')->after('proof_sha256');
            $table->timestamp('proof_security_scanned_at')->nullable()->after('proof_malware_scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'proof_mime_type',
                'proof_sha256',
                'proof_malware_scan_status',
                'proof_security_scanned_at',
            ]);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn([
                'mime_type',
                'sha256',
                'malware_scan_status',
                'security_scanned_at',
            ]);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lifecycle_changed_by');
            $table->dropColumn(['lifecycle_status', 'lifecycle_reason', 'lifecycle_changed_at']);
        });

        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->dropColumn('registration_fee');
        });
    }
};
