<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TABLES = ['users', 'units', 'study_programs', 'registration_pathways', 'registration_openings', 'registrations', 'parent_infos', 'documents', 'payments', 'virtual_accounts', 'virtual_account_batches', 'admission_tests', 'admission_test_results', 'selections', 'announcements', 'audit_logs', 'unit_configurations', 'test_sessions', 'test_bookings', 'payment_receipts'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'uuid')) {
                Schema::table($name, fn (Blueprint $table) => $table->uuid('uuid')->nullable()->unique());
            }
            DB::table($name)->whereNull('uuid')->orderBy('id')->chunkById(250, function ($rows) use ($name): void {
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });
        }

        DB::table('notifications')->orderBy('id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $data = json_decode($row->data, true);
                if (! is_array($data)) {
                    continue;
                }
                foreach ($data['actions'] ?? [] as $index => $action) {
                    if (! is_string($action['url'] ?? null)) {
                        continue;
                    }
                    $data['actions'][$index]['url'] = preg_replace_callback(
                        '~(/(?:pendaftar/(?:status|pembayaran|dokumen|jadwal-tes|registrations)|admin/registrations|registration)/)([0-9]+)(?=/|\?|$)~',
                        fn (array $match): string => $match[1].(DB::table('registrations')->where('id', $match[2])->value('uuid') ?? $match[2]),
                        $action['url'],
                    );
                    $data['actions'][$index]['url'] = preg_replace_callback(
                        '~(/admin/payments/)([0-9]+)(?=/|\?|$)~',
                        fn (array $match): string => $match[1].(DB::table('payments')->where('id', $match[2])->value('uuid') ?? $match[2]),
                        $data['actions'][$index]['url'],
                    );
                }
                DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
