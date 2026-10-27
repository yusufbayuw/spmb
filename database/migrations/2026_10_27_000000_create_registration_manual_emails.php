<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_manual_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_delivery_attempt_id')->unique()
                ->constrained('mail_delivery_attempts')->cascadeOnDelete();
            $table->string('subject', 150);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_manual_emails');
    }
};
