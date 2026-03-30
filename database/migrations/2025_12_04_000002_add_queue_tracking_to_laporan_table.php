<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->enum('file_status', ['pending', 'processing', 'completed', 'failed'])->nullable()->after('file_size');
            $table->string('job_id')->nullable()->after('file_status');
            $table->timestamp('file_processed_at')->nullable()->after('job_id');
            $table->text('file_error')->nullable()->after('file_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropColumn(['file_status', 'job_id', 'file_processed_at', 'file_error']);
        });
    }
};
