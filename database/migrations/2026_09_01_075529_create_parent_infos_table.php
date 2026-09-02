<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->onDelete('cascade');
            
            // Data Ayah
            $table->string('father_name', 150);
            $table->string('father_nik', 16)->nullable();
            $table->string('father_birth_place', 100)->nullable();
            $table->date('father_birth_date')->nullable();
            $table->string('father_education', 50)->nullable();
            $table->string('father_occupation', 100)->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_email', 100)->nullable();
            $table->decimal('father_income', 15, 2)->nullable();
            
            // Data Ibu
            $table->string('mother_name', 150);
            $table->string('mother_nik', 16)->nullable();
            $table->string('mother_birth_place', 100)->nullable();
            $table->date('mother_birth_date')->nullable();
            $table->string('mother_education', 50)->nullable();
            $table->string('mother_occupation', 100)->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_email', 100)->nullable();
            $table->decimal('mother_income', 15, 2)->nullable();
            
            // Data Wali (Opsional)
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->text('guardian_address')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_infos');
    }
};