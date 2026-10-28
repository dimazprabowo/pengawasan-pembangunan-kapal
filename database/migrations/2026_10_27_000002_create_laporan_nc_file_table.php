<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // File bukti NC (before/after). Satu file bisa di-link ke beberapa NC
        // lewat tabel pivot laporan_nc_file_link — makanya file berdiri sendiri.
        Schema::create('laporan_nc_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('data_kapal_id');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_nc_file');
    }
};
