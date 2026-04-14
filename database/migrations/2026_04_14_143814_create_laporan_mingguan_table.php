<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('laporan_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jenis_kapal_id')->nullable()->constrained('jenis_kapal')->nullOnDelete();
            $table->string('judul');
            $table->date('tanggal_laporan');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_laporan');
            $table->index('user_id');
            $table->index('jenis_kapal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_mingguan');
    }
};
