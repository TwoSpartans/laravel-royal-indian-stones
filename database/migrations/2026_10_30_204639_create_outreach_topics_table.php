<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_topics', function (Blueprint $table) {
            $table->id();
            $table->string('phrase');
            $table->string('intent');
            $table->string('target_url')->nullable();
            $table->string('country')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_topics');
    }
};
