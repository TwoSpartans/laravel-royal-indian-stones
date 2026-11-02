<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_runs', function (Blueprint $table) {
            $table->id();
            $table->string('sitemap_group', 32)->nullable();
            $table->string('trigger', 32);
            $table->string('status', 16);
            $table->unsignedInteger('url_count')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('sitemap_exclusions', function (Blueprint $table) {
            $table->id();
            $table->string('sitemap_group', 32);
            $table->string('loc', 2048);
            $table->string('reason');
            $table->timestamp('recorded_at');
            $table->index('sitemap_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_exclusions');
        Schema::dropIfExists('sitemap_runs');
    }
};
