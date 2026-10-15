<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('introduction')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_published')->default(true);
        });

        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('post_category_id')->nullable()->constrained('post_categories')->nullOnDelete();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('body');
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });

        Schema::create('seo_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('site_name');
            $table->string('url')->nullable();
            $table->string('status')->default('noted');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_submissions');
        Schema::dropIfExists('comments');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_category_id');
        });
        Schema::dropIfExists('post_categories');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('introduction');
        });
    }
};
