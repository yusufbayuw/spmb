<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_consents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_opening_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_configuration_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('title_snapshot', 180);
            $table->longText('content_snapshot');
            $table->text('confirmation_snapshot');
            $table->char('content_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'registration_opening_id', 'unit_configuration_id'],
                'registration_consent_context',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_consents');
    }
};
