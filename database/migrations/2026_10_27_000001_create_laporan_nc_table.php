<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_nc', function (Blueprint $table) {
            $table->id();
            // NC diinput dari laporan harian; ikut terhapus bila laporannya dihapus
            $table->foreignId('laporan_harian_id')->constrained('laporan_harian')->cascadeOnDelete();
            // Denormalisasi untuk scope List NC & rekap mingguan tanpa join
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            // Kategori = Work Group Kurva-S (null = "Lainnya"), snapshot nama agar histori utuh
            $table->foreignId('kurva_s_work_group_id')->nullable()->constrained('kurva_s_work_groups')->nullOnDelete();
            $table->string('kategori')->nullable();
            // Nomor urut per kapal, diisi otomatis service (NC-001, NC-002, ...)
            $table->string('nomor')->nullable();
            $table->text('kendala');
            $table->text('tindakan')->nullable();
            $table->string('pic')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('laporan_harian_id');
            $table->index(['data_kapal_id', 'status']);
            $table->index('kategori');
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc');
    }
};
