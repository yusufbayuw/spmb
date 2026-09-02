<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_account_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('virtual_account_batches', function (Blueprint $table): void {
            $table->dropColumn(['bank', 'default_amount', 'expires_at']);
        });

        Schema::table('virtual_accounts', function (Blueprint $table): void {
            $table->dropColumn('amount');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('amount', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('payments')->whereNull('amount')->update(['amount' => 0]);

        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('amount', 15, 2)->nullable(false)->change();
        });

        Schema::table('virtual_accounts', function (Blueprint $table): void {
            $table->decimal('amount', 15, 2)->nullable();
        });

        Schema::table('virtual_account_batches', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bank', 50)->nullable();
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }
};
