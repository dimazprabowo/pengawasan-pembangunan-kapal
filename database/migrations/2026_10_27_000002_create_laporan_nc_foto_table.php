<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto bukti temuan NC — 1:1 dengan NC (pola laporan_lampiran ↔ aktivitas).
        // Diunggah dari form Laporan Harian, diproses async (crop + WebP).
        Schema::create('laporan_nc_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_nc_id')->unique()->constrained('laporan_nc')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc_foto');
    }
};
