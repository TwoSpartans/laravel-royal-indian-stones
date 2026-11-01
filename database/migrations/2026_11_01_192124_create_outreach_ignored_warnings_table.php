<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_ignored_warnings', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('gap');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'gap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_ignored_warnings');
    }
};
