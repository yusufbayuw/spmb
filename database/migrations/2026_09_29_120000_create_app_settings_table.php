<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('portal_name', 180);
            $table->string('organization_name', 180);
            $table->string('website_url')->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('service_hours', 180)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('theme_color', 20)->default('#2563eb');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
