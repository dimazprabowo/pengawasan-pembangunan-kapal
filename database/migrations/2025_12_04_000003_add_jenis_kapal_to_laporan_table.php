<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->foreignId('jenis_kapal_id')->nullable()->after('user_id')->constrained('jenis_kapal')->nullOnDelete();
            $table->index('jenis_kapal_id');
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropForeign(['jenis_kapal_id']);
            $table->dropIndex(['jenis_kapal_id']);
            $table->dropColumn('jenis_kapal_id');
        });
    }
};
