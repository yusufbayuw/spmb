<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_payment_instructions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained('units')->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_payment_instructions');
    }
};
