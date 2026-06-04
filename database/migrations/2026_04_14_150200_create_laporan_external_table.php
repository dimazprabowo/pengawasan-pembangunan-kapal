<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_external', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_mingguan_id')->constrained('laporan_mingguan')->cascadeOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->timestamps();

            $table->index('laporan_mingguan_id');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_external');
    }
};
