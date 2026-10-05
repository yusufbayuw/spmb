<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->timestamp('test_schedule_confirmed_at')->nullable()->after('documents_verified_at');
        });

        DB::table('registrations')
            ->whereIn('current_stage', [
                'selection',
                'announcement',
                'waiting_list',
                'admission_offer',
                're_registration',
                'enrollment',
                'completed',
            ])
            ->update([
                'test_schedule_confirmed_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn('test_schedule_confirmed_at');
        });
    }
};
