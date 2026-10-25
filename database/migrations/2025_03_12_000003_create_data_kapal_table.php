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
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('galangan_id')->nullable()->constrained('galangan')->nullOnDelete();
            $table->string('nama', 255);
            $table->text('deskripsi')->nullable();
            $table->json('spesifikasi')->nullable()
                ->comment('Daftar spesifikasi dinamis [{item, keterangan}]');
            $table->date('tanggal_mulai_kontrak')->nullable()
                ->comment('Tanggal mulai kontrak pembangunan (hari kalender)');
            $table->unsignedSmallInteger('durasi_kontrak_hari')->nullable()
                ->comment('Durasi kontrak dalam hari kalender');
            $table->string('template_path_harian')->nullable();
            $table->string('template_path_mingguan')->nullable();
            $table->string('template_path_bulanan')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
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
