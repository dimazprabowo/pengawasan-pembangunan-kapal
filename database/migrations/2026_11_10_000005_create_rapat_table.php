<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog jenis rapat (mingguan, bulanan, dst) — dinamis, dibagikan
        // lintas kapal, dikelola dari tab Rapat Data Proyek. Nilai awal
        // di-seed dari enum App\Enums\RapatJenis.
        Schema::create('rapat_jenis', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rapat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('rapat_jenis_id')->constrained('rapat_jenis')->restrictOnDelete();
            $table->string('lokasi')->nullable();
            $table->string('topik')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index('tanggal');
            $table->index(['data_kapal_id', 'tanggal']);
            $table->index('rapat_jenis_id');
        });

        // Lampiran rapat — banyak file gambar/PDF per rapat (notulen, daftar
        // hadir, dokumentasi). Diunggah dari tab Rapat Data Proyek, diproses
        // async (crop + WebP untuk gambar) oleh ProcessRapatFile.
        Schema::create('rapat_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
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

            $table->index('rapat_id');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapat_file');
        Schema::dropIfExists('rapat');
        Schema::dropIfExists('rapat_jenis');
    }
};
