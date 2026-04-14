<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_peralatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_harian_id')->constrained('laporan_harian')->cascadeOnDelete();
            $table->string('jenis')->nullable();
            $table->integer('jumlah')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('laporan_harian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_peralatan');
    }
};
