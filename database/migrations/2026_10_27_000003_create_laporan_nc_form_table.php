<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Form NC (Non Conformance Report): dokumen Word digenerate dari NC
        // (draft), dicetak & ditandatangani, lalu scan TTD diunggah kembali.
        // - temuan    : per laporan harian (laporan_harian_id terisi)
        // - penutupan : lintas hari; upload TTD menutup semua NC di dalamnya
        Schema::create('laporan_nc_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->foreignId('laporan_harian_id')->nullable()->constrained('laporan_harian')->cascadeOnDelete();
            $table->enum('jenis', ['temuan', 'penutupan']);
            // Nomor NCR urut per kapal, diawali kode kapal ({kode}-NCR-001, -002, ...)
            $table->string('nomor');
            $table->date('tanggal');
            // File scan bertanda tangan — null = masih draft (menunggu TTD)
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['data_kapal_id', 'nomor']);
            $table->index(['data_kapal_id', 'jenis']);
            $table->index('laporan_harian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc_form');
    }
};
