<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kapal', function (Blueprint $table) {
            $table->foreignId('galangan_id')->nullable()->after('company_id')->constrained('galangan')->nullOnDelete();
            $table->index('galangan_id');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_kapal', function (Blueprint $table) {
            $table->dropForeign(['galangan_id']);
            $table->dropIndex(['galangan_id']);
            $table->dropColumn('galangan_id');
        });
    }
};
