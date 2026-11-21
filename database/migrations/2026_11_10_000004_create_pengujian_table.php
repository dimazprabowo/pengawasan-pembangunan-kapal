<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengujian_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nama_pengujian');
            $table->date('tanggal')->nullable();
            $table->foreignId('pengujian_status_id')->constrained('pengujian_statuses')->restrictOnDelete();
            $table->string('hasil')->nullable()
                ->comment('Hasil pengujian (mis. Lulus, Memuaskan, NDT OK)');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index('tanggal');
            $table->index(['data_kapal_id', 'tanggal']);
            $table->index('pengujian_status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengujian');
        Schema::dropIfExists('pengujian_statuses');
    }
};
