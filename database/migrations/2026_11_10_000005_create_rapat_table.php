<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('jenis', ['mingguan', 'bulanan', 'lainnya'])->default('mingguan');
            $table->string('lokasi')->nullable();
            $table->string('topik')->nullable();
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
        Schema::dropIfExists('rapat');
    }
};
