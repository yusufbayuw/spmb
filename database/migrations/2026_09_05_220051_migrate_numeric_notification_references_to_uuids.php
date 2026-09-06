<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')->orderBy('id')->chunk(250, function ($notifications): void {
            foreach ($notifications as $notification) {
                $data = json_decode($notification->data, true);
                if (! is_array($data)) {
                    continue;
                }

                $this->replaceReference($data, 'registration', 'registrations');
                $this->replaceReference($data, 'unit', 'units');
                if (is_array($data['metadata'] ?? null)) {
                    $this->replaceReference($data['metadata'], 'payment', 'payments');
                    $this->replaceReference($data['metadata'], 'announcement', 'announcements');
                    $this->replaceReference($data['metadata'], 'changed_by', 'users');
                }

                DB::table('notifications')->where('id', $notification->id)->update([
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function down(): void
    {
        // UUIDs cannot be safely converted back to internal numeric keys.
    }

    private function replaceReference(array &$data, string $name, string $table): void
    {
        $key = $name.'_id';
        $legacyKey = $name === 'changed_by' ? 'changed_by' : $key;

        if (! isset($data[$key]) && ! isset($data[$legacyKey])) {
            return;
        }

        $referenceId = $data[$key] ?? $data[$legacyKey];
        $uuid = DB::table($table)->where('id', $referenceId)->value('uuid');
        if ($uuid) {
            $data[$name.'_uuid'] = $uuid;
            unset($data[$key]);
            unset($data[$legacyKey]);
        }
    }
};
