<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_pathways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'name'], 'registration_pathways_unit_name_unique');
            $table->index(
                ['unit_id', 'is_active', 'archived_at'],
                'registration_pathways_unit_availability',
            );
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->foreignId('registration_pathway_id')
                ->nullable()
                ->after('registration_opening_id')
                ->constrained('registration_pathways')
                ->restrictOnDelete();
        });

        $now = now();

        DB::table('registration_openings')
            ->select(['id', 'unit_id', 'pathway', 'created_by'])
            ->whereNotNull('pathway')
            ->orderBy('id')
            ->get()
            ->each(function (object $opening) use ($now): void {
                $name = trim((string) $opening->pathway);

                if ($name === '') {
                    return;
                }

                DB::table('registration_pathways')->insertOrIgnore([
                    'unit_id' => $opening->unit_id,
                    'name' => $name,
                    'is_active' => true,
                    'created_by' => $opening->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $pathwayId = DB::table('registration_pathways')
                    ->where('unit_id', $opening->unit_id)
                    ->where('name', $name)
                    ->value('id');

                DB::table('registrations')
                    ->where('registration_opening_id', $opening->id)
                    ->update(['registration_pathway_id' => $pathwayId]);
            });

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_unique_offering')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropUnique('registration_openings_unique_offering');
            });
        }

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_unique_period')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropUnique('registration_openings_unique_period');
            });
        }

        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->index(
                ['unit_id', 'study_program_id', 'academic_year', 'wave'],
                'registration_openings_period_lookup',
            );
            $table->index(
                ['status', 'opened_at', 'closed_at'],
                'registration_openings_schedule_status',
            );
        });
    }

    public function down(): void
    {
        if ($this->hasNamedIndex('registration_openings', 'registration_openings_period_lookup')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropIndex('registration_openings_period_lookup');
            });
        }

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_schedule_status')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropIndex('registration_openings_schedule_status');
            });
        }

        DB::table('registration_openings')
            ->select(['id', 'unit_id'])
            ->where('pathway', '')
            ->orderBy('id')
            ->get()
            ->each(function (object $opening): void {
                $name = DB::table('registrations')
                    ->join(
                        'registration_pathways',
                        'registration_pathways.id',
                        '=',
                        'registrations.registration_pathway_id',
                    )
                    ->where('registrations.registration_opening_id', $opening->id)
                    ->value('registration_pathways.name')
                    ?? DB::table('registration_pathways')
                        ->where('unit_id', $opening->unit_id)
                        ->orderBy('id')
                        ->value('name')
                    ?? 'Reguler';

                DB::table('registration_openings')
                    ->where('id', $opening->id)
                    ->update(['pathway' => $name]);
            });

        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->unique(
                ['unit_id', 'study_program_id', 'academic_year', 'wave', 'pathway'],
                'registration_openings_unique_offering',
            );
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registration_pathway_id');
        });

        Schema::dropIfExists('registration_pathways');
    }

    private function hasNamedIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
