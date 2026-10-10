<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_readiness_attestations', function (Blueprint $table): void {
            $table->id();
            $table->string('deployment_id', 64);
            $table->string('release_sha', 64);
            $table->string('check_id', 80);
            $table->string('status', 16);
            $table->text('evidence')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['deployment_id', 'release_sha', 'check_id'], 'readiness_attestation_release_unique');
        });

        Schema::create('production_readiness_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('deployment_id', 64);
            $table->string('release_sha', 64);
            $table->string('environment', 32);
            $table->string('status', 24);
            $table->unsignedInteger('failed_count');
            $table->unsignedInteger('warning_count');
            $table->unsignedInteger('pending_count');
            $table->json('summary');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['deployment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_readiness_snapshots');
        Schema::dropIfExists('production_readiness_attestations');
    }
};
