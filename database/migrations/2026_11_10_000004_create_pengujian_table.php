<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengujian_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nama_pengujian');
            $table->date('tanggal')->nullable();
            $table->foreignId('pengujian_status_id')->constrained('pengujian_statuses')->restrictOnDelete();
            $table->string('hasil')->nullable()
                ->comment('Hasil pengujian (mis. Lulus, Memuaskan, NDT OK)');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index('tanggal');
            $table->index(['data_kapal_id', 'tanggal']);
            $table->index('pengujian_status_id');
        });

        // Lampiran pengujian — banyak file gambar/PDF per pengujian (sertifikat,
        // foto uji, dokumen hasil). Diunggah dari tab Pengujian Data Proyek,
        // diproses async (crop + WebP untuk gambar) oleh ProcessPengujianFile.
        Schema::create('pengujian_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengujian_id')->constrained('pengujian')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pengujian_id');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengujian_file');
        Schema::dropIfExists('pengujian');
        Schema::dropIfExists('pengujian_statuses');
    }
};
