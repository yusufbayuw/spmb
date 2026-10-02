<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certification_programs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('theory_weight')->default(40)->after('passing_score');
            $table->unsignedTinyInteger('practical_weight')->default(60)->after('theory_weight');
            $table->unsignedTinyInteger('practical_passing_score')->default(80)->after('practical_weight');
        });
    }

    public function down(): void
    {
        Schema::table('certification_programs', function (Blueprint $table): void {
            $table->dropColumn(['theory_weight', 'practical_weight', 'practical_passing_score']);
        });
    }
};
