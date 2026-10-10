<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('origin', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedBigInteger('source_id');
            $table->string('recipient_email', 254);
            $table->text('reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'type', 'created_at'], 'mail_delivery_registration_type_time_idx');
            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_delivery_attempts');
    }
};
