<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lampiran NC — banyak file Open/Close untuk setiap NC.
        // Diunggah dari form Laporan Harian, diproses async (crop + WebP).
        Schema::create('laporan_nc_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_nc_id')->constrained('laporan_nc')->cascadeOnDelete();
            $table->string('kategori')->default('open');
            $table->json('rendered_pages')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
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
