<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_images', function (Blueprint $table) {
            $table->id();
            $table->string('section', 32);
            $table->string('slot', 80);
            $table->string('path')->nullable();
            $table->string('alt')->nullable();
            $table->timestamps();

            $table->unique(['section', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_images');
    }
};
