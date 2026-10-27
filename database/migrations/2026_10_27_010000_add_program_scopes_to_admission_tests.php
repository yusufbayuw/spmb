<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable retains the exact meaning of legacy study_program_id
        // until a test's program scope is explicitly edited.
        Schema::table('admission_tests', function (Blueprint $table): void {
            $table->json('study_program_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admission_tests', function (Blueprint $table): void {
            $table->dropColumn('study_program_ids');
        });
    }
};
