<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->timestamp('paused_at')->nullable()->after('closed_at')->index();
            $table->foreignId('paused_by')
                ->nullable()
                ->after('paused_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn('paused_at');
        });
    }
};
