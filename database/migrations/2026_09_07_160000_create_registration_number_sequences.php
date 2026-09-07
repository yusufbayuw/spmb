<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_number_sequences', function (Blueprint $table): void {
            $table->foreignId('unit_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        DB::table('registrations')->update(['registration_number' => null]);

        $units = DB::table('units')->orderBy('id')->get(['id', 'code']);

        foreach ($units as $unit) {
            $registrations = DB::table('registrations')
                ->leftJoin('registration_openings', 'registration_openings.id', '=', 'registrations.registration_opening_id')
                ->where('registrations.unit_id', $unit->id)
                ->where(function ($query): void {
                    $query->whereNotNull('registrations.payment_verified_at')
                        ->orWhereNotNull('registrations.applicant_card_issued_at');
                })
                ->orderByRaw('COALESCE(registrations.payment_verified_at, registrations.applicant_card_issued_at, registrations.created_at)')
                ->orderBy('registrations.id')
                ->get([
                    'registrations.id',
                    'registrations.applicant_card_issued_at',
                    'registration_openings.academic_year',
                    'registrations.created_at',
                ]);

            $sequence = 0;

            foreach ($registrations as $registration) {
                $sequence++;

                $year = $registration->academic_year
                    ? str_replace(['/', '-'], '', $registration->academic_year)
                    : substr((string) $registration->created_at, 0, 4);

                $registrationNumber = 'REG-'.$unit->code.'-'.$year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

                $updates = [
                    'registration_number' => $registrationNumber,
                ];

                if ($registration->applicant_card_issued_at) {
                    $updates['applicant_card_number'] = preg_replace('/^REG-/', 'KARTU-', $registrationNumber);
                }

                DB::table('registrations')
                    ->where('id', $registration->id)
                    ->update($updates);
            }

            DB::table('registration_number_sequences')->insert([
                'unit_id' => $unit->id,
                'last_number' => $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_number_sequences');
    }
};
