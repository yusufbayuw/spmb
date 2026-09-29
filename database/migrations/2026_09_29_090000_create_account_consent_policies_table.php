<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_consent_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 20);
            $table->string('terms_title', 180);
            $table->longText('terms_content');
            $table->string('privacy_title', 180);
            $table->longText('privacy_content');
            $table->text('required_confirmation_text');
            $table->boolean('marketing_enabled')->default(true);
            $table->text('marketing_text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'version'], 'account_policy_status_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_consent_policies');
    }
};
