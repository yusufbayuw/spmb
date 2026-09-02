<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->string('registration_number', 50)->nullable()->unique();
            
            // Data Calon Siswa
            $table->string('nik', 16)->unique();
            $table->string('full_name', 150);
            $table->string('nickname', 50)->nullable();
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place', 100);
            $table->date('birth_date');
            $table->string('religion', 50)->default('Islam');
            $table->integer('child_order')->nullable();
            $table->integer('siblings_count')->default(0);
            $table->text('home_address');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            
            // Data Sekolah Asal
            $table->string('previous_school', 150)->nullable();
            $table->string('previous_school_address', 255)->nullable();
            $table->year('graduation_year')->nullable();
            
            // Status
            $table->enum('status', [
                'draft',
                'submitted',
                'verified',
                'payment_pending',
                'payment_uploaded',
                'payment_verified',
                'accepted',
                'rejected',
                'waiting_list'
            ])->default('draft');
            
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};