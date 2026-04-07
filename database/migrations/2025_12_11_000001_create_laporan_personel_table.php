<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_personel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('jabatan')->nullable();
            $table->string('status')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('laporan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_personel');
    }
};
