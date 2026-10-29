<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_exams', function (Blueprint $table): void {
            $table->foreignId('admission_test_id')->nullable()
                ->constrained('admission_tests')->restrictOnDelete();
        });

        Schema::create('online_exam_result_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('online_exam_attempt_id')->unique()
                ->constrained('online_exam_attempts')->cascadeOnDelete();
            $table->foreignId('admission_test_result_id')->unique()
                ->constrained('admission_test_results')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('source_percentage', 5, 2);
            $table->string('result', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_result_transfers');
        Schema::table('online_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admission_test_id');
        });
    }
};
