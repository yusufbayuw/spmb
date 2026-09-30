<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            if (! Schema::hasColumn('unit_configurations', 'applicant_card_header_label')) {
                $table->string('applicant_card_header_label', 80)
                    ->nullable()
                    ->after('registration_number_digits');
            }

            if (! Schema::hasColumn('unit_configurations', 'applicant_card_header_title')) {
                $table->string('applicant_card_header_title', 180)
                    ->nullable()
                    ->after('applicant_card_header_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unit_configurations', function (Blueprint $table): void {
            if (Schema::hasColumn('unit_configurations', 'applicant_card_header_title')) {
                $table->dropColumn('applicant_card_header_title');
            }

            if (Schema::hasColumn('unit_configurations', 'applicant_card_header_label')) {
                $table->dropColumn('applicant_card_header_label');
            }
        });
    }
};
