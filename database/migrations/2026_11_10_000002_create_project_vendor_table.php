<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_lingkups', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_kapal_id')->constrained('data_kapal')->cascadeOnDelete();
            $table->string('nama');
            $table->foreignId('lingkup_id')->constrained('vendor_lingkups')->restrictOnDelete();
            $table->index('lingkup_id');
            $table->string('kontak')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('bulan_ke')
                ->comment('Bulan proyek mulai vendor dilibatkan — opsi dari kurva_s_rencana (hasil generate plan); tampil kumulatif di laporan bulanan bulan_ke >= nilai ini');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['data_kapal_id', 'bulan_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_vendor');
        Schema::dropIfExists('vendor_lingkups');
    }
};
