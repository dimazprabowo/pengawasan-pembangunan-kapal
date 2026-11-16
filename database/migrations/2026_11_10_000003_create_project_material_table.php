<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->enum('kategori', ['steel_plate', 'system_komponen', 'lainnya'])->default('lainnya');
            $table->string('nama_item');
            $table->string('spesifikasi')->nullable();
            $table->decimal('jumlah', 12, 2)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->enum('status', ['direncanakan', 'pengadaan', 'tiba', 'terpasang'])->default('direncanakan');
            $table->date('tanggal')->nullable()
                ->comment('Tanggal estimasi / aktual kedatangan material');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_material');
    }
};
