<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_accounts', function (Blueprint $table): void {
            $table->foreignId('study_program_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('study_programs')
                ->restrictOnDelete();

            $table->index(
                ['unit_id', 'study_program_id', 'status'],
                'virtual_accounts_unit_program_status'
            );
        });
    }

    public function down(): void
    {
        Schema::table('virtual_accounts', function (Blueprint $table): void {
            $table->dropIndex('virtual_accounts_unit_program_status');
            $table->dropConstrainedForeignId('study_program_id');
        });
    }
};
