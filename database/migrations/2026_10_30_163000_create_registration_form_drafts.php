<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_form_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_configuration_id')->constrained()->cascadeOnDelete();
            $table->longText('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'registration_opening_id'], 'registration_form_drafts_owner_opening_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_form_drafts');
    }
};
