<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->boolean('payment_enabled')->default(true);
            $table->boolean('documents_enabled')->default(true);
            $table->boolean('tests_enabled')->default(false);
            $table->json('fields');
            $table->json('document_requirements');
            $table->json('test_definitions');
            $table->boolean('legacy')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'version']);
        });
        Schema::table('registrations', function (Blueprint $table): void {
            $table->foreignId('unit_configuration_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('custom_answers')->nullable();
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('requirement_key')->nullable()->index();
            $table->unsignedInteger('attachment_index')->default(0);
            $table->timestamp('superseded_at')->nullable();
        });
        Schema::create('test_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_test_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('booking_closes_at');
            $table->string('location');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('capacity');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('test_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('test_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['registration_id', 'admission_test_id']);
        });
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->json('details');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
        DB::table('documents')->update(['requirement_key' => DB::raw('type')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('test_bookings');
        Schema::dropIfExists('test_sessions');
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn(['requirement_key', 'attachment_index', 'superseded_at']));
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_configuration_id');
            $table->dropColumn('custom_answers');
        });
        Schema::dropIfExists('unit_configurations');
    }
};
