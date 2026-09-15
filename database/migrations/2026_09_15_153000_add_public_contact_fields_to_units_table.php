<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->string('public_contact_name', 120)->nullable()->after('description');
            $table->string('public_email', 150)->nullable()->after('public_contact_name');
            $table->string('public_phone', 30)->nullable()->after('public_email');
            $table->string('public_whatsapp', 30)->nullable()->after('public_phone');
            $table->string('public_service_hours', 120)->nullable()->after('public_whatsapp');
            $table->string('public_website_url', 255)->nullable()->after('public_service_hours');
            $table->text('public_address')->nullable()->after('public_website_url');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn([
                'public_contact_name',
                'public_email',
                'public_phone',
                'public_whatsapp',
                'public_service_hours',
                'public_website_url',
                'public_address',
            ]);
        });
    }
};
