<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('material_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->foreignId('material_category_id')->constrained('material_categories')->restrictOnDelete();
            $table->string('nama_item');
            $table->string('spesifikasi')->nullable();
            $table->decimal('jumlah', 12, 2)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->foreignId('material_status_id')->constrained('material_statuses')->restrictOnDelete();
            $table->date('tanggal')->nullable()
                ->comment('Tanggal estimasi / aktual kedatangan material');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_kapal_id');
            $table->index(['data_kapal_id', 'tanggal']);
            $table->index('material_category_id');
            $table->index('material_status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_material');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('material_statuses');
    }
};
