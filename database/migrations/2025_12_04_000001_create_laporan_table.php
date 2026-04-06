<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipe', ['harian', 'mingguan', 'bulanan']);
            $table->string('judul');
            $table->date('tanggal_laporan');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipe', 'tanggal_laporan']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};
