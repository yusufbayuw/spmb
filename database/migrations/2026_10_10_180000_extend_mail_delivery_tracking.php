<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mail_delivery_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('mail_delivery_attempts', fn (Blueprint $table) =>
            $table->dropColumn(['attempt_count', 'last_attempted_at']));
    }
};
