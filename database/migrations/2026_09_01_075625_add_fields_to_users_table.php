<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 20)->default('user')->after('phone'); // admin, tu, user
            $table->foreignId('unit_id')->nullable()->after('role')->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true)->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['phone', 'role', 'unit_id', 'is_active']);
        });
    }
};