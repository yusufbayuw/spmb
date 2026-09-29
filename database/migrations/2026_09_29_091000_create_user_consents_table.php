<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_consent_policy_id')->constrained()->restrictOnDelete();
            $table->string('consent_type', 40);
            $table->string('title_snapshot', 180);
            $table->longText('content_snapshot');
            $table->text('confirmation_snapshot');
            $table->char('content_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'account_consent_policy_id', 'consent_type'],
                'user_consent_policy_type',
            );
            $table->index(['user_id', 'accepted_at'], 'user_consent_user_accepted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
