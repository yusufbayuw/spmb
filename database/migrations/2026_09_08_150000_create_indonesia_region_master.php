<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->string('code', 2)->primary();
            $table->string('name', 150)->index();
            $table->timestamps();
        });

        Schema::create('regencies', function (Blueprint $table): void {
            $table->string('code', 5)->primary();
            $table->string('province_code', 2);
            $table->string('name', 150)->index();
            $table->timestamps();

            $table->foreign('province_code')->references('code')->on('provinces')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['province_code', 'name']);
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->string('code', 8)->primary();
            $table->string('regency_code', 5);
            $table->string('name', 150)->index();
            $table->timestamps();

            $table->foreign('regency_code')->references('code')->on('regencies')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['regency_code', 'name']);
        });

        Schema::create('villages', function (Blueprint $table): void {
            $table->string('code', 13)->primary();
            $table->string('district_code', 8);
            $table->string('name', 150)->index();
            $table->timestamps();

            $table->foreign('district_code')->references('code')->on('districts')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['district_code', 'name']);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->string('province_code', 2)->nullable()->index()->after('province');
            $table->string('city_code', 5)->nullable()->index()->after('province_code');
            $table->string('district_code', 8)->nullable()->index()->after('city_code');
            $table->string('village_code', 13)->nullable()->index()->after('district_code');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropColumn(['province_code', 'city_code', 'district_code', 'village_code']);
        });

        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};
