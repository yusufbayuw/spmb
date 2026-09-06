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
        Schema::create('admission_quotas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('registration_opening_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_pathway_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('offer_expires_in_hours')->default(72);
            $table->unsignedInteger('re_registration_due_in_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['registration_opening_id', 'registration_pathway_id', 'is_active'], 'admission_quota_scope_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_quotas');
    }
};
