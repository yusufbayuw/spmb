<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_payment_instructions', function (Blueprint $table): void {
            $table->boolean('show_on_payment_page')->default(true);
            $table->boolean('show_on_next_action')->default(false);
            $table->boolean('show_on_payment_summary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('unit_payment_instructions', function (Blueprint $table): void {
            $table->dropColumn([
                'show_on_payment_page',
                'show_on_next_action',
                'show_on_payment_summary',
            ]);
        });
    }
};
