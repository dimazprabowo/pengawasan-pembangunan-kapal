<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->string('doc_path')->nullable()->after('file_error');
            $table->string('doc_name')->nullable()->after('doc_path');
            $table->enum('doc_status', ['pending', 'processing', 'completed', 'failed'])->nullable()->after('doc_name');
            $table->timestamp('doc_generated_at')->nullable()->after('doc_status');
            $table->text('doc_error')->nullable()->after('doc_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropColumn(['doc_path', 'doc_name', 'doc_status', 'doc_generated_at', 'doc_error']);
        });
    }
};
