<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();
            $table->unsignedBigInteger('pdf_bytes')->nullable();
            $table->timestamp('pdf_uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_settings');
    }
};
