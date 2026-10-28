<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link file ↔ NC dengan peran before/after per NC (file yang sama bisa
        // jadi 'before' di NC-A dan 'after' di NC-B).
        Schema::create('laporan_nc_file_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_nc_id')->constrained('laporan_nc')->cascadeOnDelete();
            $table->foreignId('laporan_nc_file_id')->constrained('laporan_nc_file')->cascadeOnDelete();
            $table->enum('kind', ['before', 'after']);
            $table->timestamps();

            $table->unique(['laporan_nc_id', 'laporan_nc_file_id', 'kind'], 'nc_file_link_unique');
            $table->index('laporan_nc_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc_file_link');
    }
};
