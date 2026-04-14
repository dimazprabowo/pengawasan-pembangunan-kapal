<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_mingguan_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_mingguan_id')->constrained('laporan_mingguan')->cascadeOnDelete();
            $table->foreignId('laporan_harian_id')->constrained('laporan_harian')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['laporan_mingguan_id', 'laporan_harian_id']);
            $table->index('laporan_mingguan_id');
            $table->index('laporan_harian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_mingguan_harian');
    }
};
