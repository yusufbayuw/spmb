<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->boolean('seat_confirmation_enabled')->default(true);
            $table->boolean('allow_enrollment_with_deferred_payment')->default(false);
            $table->string('re_registration_deadline_policy')->default('manual');
            $table->unsignedSmallInteger('re_registration_grace_days')->default(3);
            $table->string('re_registration_bank_name', 120)->nullable();
            $table->string('re_registration_bank_number', 100)->nullable();
            $table->string('re_registration_bank_holder', 180)->nullable();
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->timestamp('re_registration_due_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('re_registration_due_at'));
        Schema::table('unit_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'seat_confirmation_enabled',
                'allow_enrollment_with_deferred_payment',
                're_registration_deadline_policy',
                're_registration_grace_days',
                're_registration_bank_name',
                're_registration_bank_number',
                're_registration_bank_holder',
            ]);
        });
    }
};
