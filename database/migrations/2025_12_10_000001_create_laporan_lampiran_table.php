<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->timestamps();

            $table->index('laporan_id');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_lampiran');
    }
};
