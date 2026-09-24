<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->string('father_workplace', 150)->nullable()->after('father_occupation');
            $table->string('mother_workplace', 150)->nullable()->after('mother_occupation');
        });
    }

    public function down(): void
    {
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->dropColumn(['father_workplace', 'mother_workplace']);
        });
    }
};
