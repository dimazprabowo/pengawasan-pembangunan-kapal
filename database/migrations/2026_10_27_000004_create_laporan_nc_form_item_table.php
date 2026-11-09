<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NC yang termuat di satu Form NC (snapshot isi dokumen)
        Schema::create('laporan_nc_form_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_nc_form_id')->constrained('laporan_nc_form')->cascadeOnDelete();
            $table->foreignId('laporan_nc_id')->constrained('laporan_nc')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['laporan_nc_form_id', 'laporan_nc_id'], 'nc_form_item_unique');
            $table->index('laporan_nc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc_form_item');
    }
};
