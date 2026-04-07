<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_kapal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('galangan_id')->nullable()->constrained('galangan')->nullOnDelete();
            $table->string('nama', 255);
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('company_id');
            $table->index('galangan_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_kapal');
    }
};
