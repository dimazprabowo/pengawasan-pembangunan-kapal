<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurva_s_work_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nama');
            $table->decimal('bobot', 8, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['data_kapal_id', 'sort_order']);
        });

        Schema::create('kurva_s_rencana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_group_id')->constrained('kurva_s_work_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('bulan_ke');
            $table->decimal('pct_rencana', 8, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['work_group_id', 'bulan_ke']);
            $table->index(['work_group_id', 'bulan_ke']);
        });

        Schema::create('kurva_s_rencana_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_group_id')->constrained('kurva_s_work_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('minggu_ke');
            $table->decimal('pct_rencana', 8, 3)->default(0);
            $table->timestamps();

            $table->unique(['work_group_id', 'minggu_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurva_s_rencana_mingguan');
        Schema::dropIfExists('kurva_s_rencana');
        Schema::dropIfExists('kurva_s_work_groups');
    }
};
