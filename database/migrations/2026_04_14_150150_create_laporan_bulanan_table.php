<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('laporan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_kapal_id')->nullable()->constrained('data_kapal')->nullOnDelete();
            $table->date('tanggal_laporan');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->text('ringkasan')->nullable();
            // BAB 3.2 problem & rekomendasi = tarikan otomatis dari rekap NC (tidak disimpan).
            $table->unsignedSmallInteger('bulan_ke')
                ->comment('Nomor bulan konstruksi (referensi ke kurva_s_rencana)');

            // Word document generation
            $table->string('doc_path')->nullable();
            $table->string('doc_name')->nullable();
            $table->enum('doc_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->timestamp('doc_generated_at')->nullable();
            $table->text('doc_error')->nullable();

            // Released PDF (hasil konversi docx via Release — di-reset saat doc digenerate ulang)
            $table->string('pdf_path')->nullable();
            $table->string('pdf_name')->nullable();
            $table->enum('pdf_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->text('pdf_error')->nullable();

            // Lembar Pengesahan tanda tangan basah (PDF upload) — saat
            // rilis, halaman pengesahan dari Word diganti file ini.
            $table->string('pengesahan_path')->nullable();
            $table->string('pengesahan_name')->nullable();
            $table->unsignedBigInteger('pengesahan_size')->nullable();
            $table->enum('pengesahan_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->timestamp('pengesahan_processed_at')->nullable();
            $table->text('pengesahan_error')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_laporan');
            $table->index('periode_mulai');
            $table->index('periode_selesai');
            $table->index('user_id');
            $table->index('data_kapal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_bulanan');
    }
};
