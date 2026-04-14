<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jenis_kapal_id')->nullable()->constrained('jenis_kapal')->nullOnDelete();
            $table->string('judul');
            $table->date('tanggal_laporan');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->string('job_id')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->text('file_error')->nullable();
            $table->string('doc_path')->nullable();
            $table->string('doc_name')->nullable();
            $table->enum('doc_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            $table->timestamp('doc_generated_at')->nullable();
            $table->text('doc_error')->nullable();
            $table->decimal('suhu', 5, 2)->nullable();
            $table->foreignId('cuaca_pagi_id')->nullable()->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_pagi_id')->nullable()->constrained('kelembaban')->nullOnDelete();
            $table->foreignId('cuaca_siang_id')->nullable()->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_siang_id')->nullable()->constrained('kelembaban')->nullOnDelete();
            $table->foreignId('cuaca_sore_id')->nullable()->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_sore_id')->nullable()->constrained('kelembaban')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_laporan');
            $table->index('user_id');
            $table->index('jenis_kapal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_harian');
    }
};
