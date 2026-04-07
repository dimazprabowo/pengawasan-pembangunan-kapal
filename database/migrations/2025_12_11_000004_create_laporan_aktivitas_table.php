<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('kategori')->nullable();
            $table->text('aktivitas')->nullable();
            $table->string('pic')->nullable();
            $table->timestamps();

            $table->index('laporan_id');
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_aktivitas');
    }
};
