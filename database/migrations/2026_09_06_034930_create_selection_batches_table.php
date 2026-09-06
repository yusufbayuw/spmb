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
        Schema::create('selection_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('registration_opening_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_pathway_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('waitlist_limit')->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('ranked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ranked_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['registration_opening_id', 'registration_pathway_id', 'status'], 'selection_batch_scope_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selection_batches');
    }
};
