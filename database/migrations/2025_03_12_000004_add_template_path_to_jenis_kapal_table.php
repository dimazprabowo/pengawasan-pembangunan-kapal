<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kapal', function (Blueprint $table) {
            $table->string('template_path')->nullable()->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_kapal', function (Blueprint $table) {
            $table->dropColumn('template_path');
        });
    }
};
