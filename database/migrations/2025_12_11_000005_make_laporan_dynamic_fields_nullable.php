<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_personel', function (Blueprint $table) {
            $table->string('jabatan')->nullable()->change();
            $table->string('status')->nullable()->change();
        });

        Schema::table('laporan_peralatan', function (Blueprint $table) {
            $table->string('jenis')->nullable()->change();
            $table->integer('jumlah')->nullable()->change();
        });

        Schema::table('laporan_consumable', function (Blueprint $table) {
            $table->string('jenis')->nullable()->change();
            $table->integer('jumlah')->nullable()->change();
        });

        Schema::table('laporan_aktivitas', function (Blueprint $table) {
            $table->string('kategori')->nullable()->change();
            $table->text('aktivitas')->nullable()->change();
            $table->string('pic')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_personel', function (Blueprint $table) {
            $table->string('jabatan')->nullable(false)->change();
            $table->string('status')->nullable(false)->change();
        });

        Schema::table('laporan_peralatan', function (Blueprint $table) {
            $table->string('jenis')->nullable(false)->change();
            $table->integer('jumlah')->nullable(false)->change();
        });

        Schema::table('laporan_consumable', function (Blueprint $table) {
            $table->string('jenis')->nullable(false)->change();
            $table->integer('jumlah')->nullable(false)->change();
        });

        Schema::table('laporan_aktivitas', function (Blueprint $table) {
            $table->string('kategori')->nullable(false)->change();
            $table->text('aktivitas')->nullable(false)->change();
            $table->string('pic')->nullable(false)->change();
        });
    }
};
