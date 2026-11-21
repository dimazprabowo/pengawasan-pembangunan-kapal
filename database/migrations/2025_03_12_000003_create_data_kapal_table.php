<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_kapal', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 6)->unique()
                ->comment('Kode unik 6 karakter, digenerate otomatis');
            $table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('galangan_id')->nullable()->constrained('galangan')->nullOnDelete();
            $table->string('nama', 255);
            $table->text('deskripsi')->nullable();
            $table->string('nama_pekerjaan')->nullable()
                ->comment('Judul pekerjaan kontrak — tampil di cover/pengesahan laporan');
            $table->string('nomor_kontrak_galangan', 150)->nullable()
                ->comment('Nomor kontrak pembangunan dengan galangan');
            $table->string('nomor_kontrak_pengawas', 150)->nullable()
                ->comment('Nomor kontrak jasa pengawasan BKI dengan pemilik pekerjaan');
            $table->string('nomor_kontrak_gambar', 150)->nullable();
            $table->json('penandatangan')->nullable()
                ->comment('Daftar penandatangan laporan [{label, nama, jabatan, instansi}] — urutan = urutan TTD');
            $table->json('spesifikasi')->nullable()
                ->comment('Daftar spesifikasi dinamis [{item, keterangan}]');
            $table->date('tanggal_mulai_pelaksanaan')->nullable()
                ->comment('Tanggal mulai pelaksanaan pembangunan (hari kalender)');
            $table->unsignedSmallInteger('waktu_pelaksanaan_hari')->nullable()
                ->comment('Waktu pelaksanaan dalam hari kalender');
            $table->json('minggu_bulan_map')->nullable()
                ->comment('Override pemetaan minggu→bulan {minggu_ke: bulan_ke} — kosong = ikut kalender mayoritas');
            $table->string('template_path_harian')->nullable();
            $table->string('template_path_mingguan')->nullable();
            $table->string('template_path_bulanan')->nullable();
            $table->string('template_path_nc')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('galangan_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_kapal');
    }
};
