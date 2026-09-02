<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'report_card',      // Raport Nilai
                'family_card',      // Kartu Keluarga
                'birth_certificate', // Akta Kelahiran
                'payment_proof',    // Bukti Pembayaran
                'supporting_document', // Dokumen Pendukung
                'photo'             // Foto Siswa
            ]);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('file_type', 10);
            $table->integer('file_size'); // in bytes
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};