<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->decimal('suhu', 5, 2)->nullable()->after('file_error');
            
            $table->foreignId('cuaca_pagi_id')->nullable()->after('suhu')->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_pagi_id')->nullable()->after('cuaca_pagi_id')->constrained('kelembaban')->nullOnDelete();
            
            $table->foreignId('cuaca_siang_id')->nullable()->after('kelembaban_pagi_id')->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_siang_id')->nullable()->after('cuaca_siang_id')->constrained('kelembaban')->nullOnDelete();
            
            $table->foreignId('cuaca_sore_id')->nullable()->after('kelembaban_siang_id')->constrained('cuaca')->nullOnDelete();
            $table->foreignId('kelembaban_sore_id')->nullable()->after('cuaca_sore_id')->constrained('kelembaban')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropForeign(['cuaca_pagi_id']);
            $table->dropForeign(['kelembaban_pagi_id']);
            $table->dropForeign(['cuaca_siang_id']);
            $table->dropForeign(['kelembaban_siang_id']);
            $table->dropForeign(['cuaca_sore_id']);
            $table->dropForeign(['kelembaban_sore_id']);
            
            $table->dropColumn([
                'suhu',
                'cuaca_pagi_id',
                'kelembaban_pagi_id',
                'cuaca_siang_id',
                'kelembaban_siang_id',
                'cuaca_sore_id',
                'kelembaban_sore_id',
            ]);
        });
    }
};
