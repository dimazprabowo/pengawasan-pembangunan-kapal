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
        Schema::create('laporan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jenis_kapal_id')->nullable()->constrained('jenis_kapal')->nullOnDelete();
            $table->date('tanggal_laporan');
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->text('ringkasan')->nullable();
            $table->unsignedSmallInteger('bulan_ke')
                ->comment('Nomor bulan konstruksi (referensi ke kurva_s_rencana)');

            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_laporan');
            $table->index('periode_mulai');
            $table->index('periode_selesai');
            $table->index('user_id');
            $table->index('jenis_kapal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_bulanan');
    }
};
