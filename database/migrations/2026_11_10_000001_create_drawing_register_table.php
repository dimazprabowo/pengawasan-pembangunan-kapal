<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_types', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('drawing_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('drawing_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->foreignId('drawing_type_id')->constrained('drawing_types')->restrictOnDelete();
            $table->string('nama');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['data_kapal_id', 'nama']);
        });

        Schema::create('drawing_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_register_id')->constrained('drawing_register')->cascadeOnDelete();
            $table->unsignedSmallInteger('revisi');
            $table->date('tanggal');
            $table->foreignId('drawing_status_id')->constrained('drawing_statuses')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['drawing_register_id', 'revisi']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_revisions');
        Schema::dropIfExists('drawing_register');
        Schema::dropIfExists('drawing_types');
        Schema::dropIfExists('drawing_statuses');
    }
};
