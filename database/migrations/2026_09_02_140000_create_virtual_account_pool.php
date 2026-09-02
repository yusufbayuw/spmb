<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_account_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('bank', 50);
            $table->string('filename');
            $table->decimal('default_amount', 15, 2);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('virtual_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('virtual_account_batches')->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('bank', 50);
            $table->string('va_number', 50);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('available');
            $table->foreignId('registration_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['bank', 'va_number']);
            $table->index(['unit_id', 'status']);
            $table->index(['status', 'expired_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('virtual_account_id')
                ->nullable()
                ->after('registration_id')
                ->unique()
                ->constrained('virtual_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('virtual_account_id');
        });

        Schema::dropIfExists('virtual_accounts');
        Schema::dropIfExists('virtual_account_batches');
    }
};
