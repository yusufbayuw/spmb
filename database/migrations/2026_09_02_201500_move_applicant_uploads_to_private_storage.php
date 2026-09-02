<?php

use App\Services\ApplicantFileStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $storage = app(ApplicantFileStorage::class);

        if (Schema::hasTable('documents')) {
            DB::table('documents')
                ->whereNotNull('file_path')
                ->select(['id', 'file_path'])
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($storage): void {
                    foreach ($rows as $row) {
                        $storage->ensurePrivate($row->file_path);
                    }
                });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'proof_path')) {
            DB::table('payments')
                ->whereNotNull('proof_path')
                ->select(['id', 'proof_path'])
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($storage): void {
                    foreach ($rows as $row) {
                        $storage->ensurePrivate($row->proof_path);
                    }
                });
        }
    }

    public function down(): void
    {
        // Security migration intentionally does not move applicant files back to public storage.
    }
};
