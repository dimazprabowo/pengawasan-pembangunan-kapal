<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nama_pengujian');
            $table->date('tanggal')->nullable();
            $table->enum('status', ['direncanakan', 'berjalan', 'selesai'])->default('direncanakan');
            $table->string('hasil')->nullable()
                ->comment('Hasil pengujian (mis. Lulus, Memuaskan, NDT OK)');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengujian');
    }
};
