<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('unit_configurations')
            ->select(['id', 'achievements_enabled', 'achievement_settings'])
            ->where('achievements_enabled', true)
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) ($row->achievement_settings ?? '{}'), true);

                    if (! is_array($settings)) {
                        $settings = [];
                    }

                    $mode = $settings['certificate_mode'] ?? null;

                    if (! in_array($mode, ['none', null, ''], true)) {
                        continue;
                    }

                    $settings['certificate_mode'] = 'optional';

                    DB::table('unit_configurations')
                        ->where('id', $row->id)
                        ->update([
                            'achievement_settings' => json_encode(
                                $settings,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no-op. After this feature is available, reverting existing
        // units to hidden certificate uploads would silently remove a configured field.
    }
};
