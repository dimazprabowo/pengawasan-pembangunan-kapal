<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nomor', 100)->comment('Nomor drawing dari galangan');
            $table->string('judul');
            $table->enum('status_produksi', ['belum', 'proses', 'selesai'])->default('belum')
                ->comment('Produksi gambar oleh galangan');
            $table->enum('status_review_mno', ['belum', 'proses', 'selesai'])->default('belum')
                ->comment('Review gambar oleh konsultansi pengawas (MNO)');
            $table->enum('status_approval_klas', ['belum', 'proses', 'selesai'])->default('belum')
                ->comment('Review/approval oleh badan klasifikasi');
            $table->unsignedSmallInteger('mno_comment_open')->default(0);
            $table->unsignedSmallInteger('mno_comment_closed')->default(0);
            $table->unsignedSmallInteger('mno_comment_informasi')->default(0);
            $table->unsignedSmallInteger('klas_comment_open')->default(0);
            $table->unsignedSmallInteger('klas_comment_closed')->default(0);
            $table->unsignedSmallInteger('klas_comment_informasi')->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_register');
    }
};
