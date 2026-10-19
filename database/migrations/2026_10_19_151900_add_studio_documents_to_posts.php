<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('body_document')->nullable();
            $table->string('content_source')->default('classic');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status')->default('published');
            $table->timestamp('scheduled_for')->nullable();
            $table->json('tags')->nullable();
            $table->string('featured_image_alt')->nullable();
        });

        Schema::create('post_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('document');
            $table->string('title');
            $table->string('slug');
            $table->string('excerpt')->default('');
            $table->text('meta_description')->nullable();
            $table->json('tags')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('post_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedBigInteger('reference_id');
            $table->string('block_id');
            $table->timestamps();
            $table->index(['post_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_references');
        Schema::dropIfExists('journal_media');
        Schema::dropIfExists('post_drafts');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'body_document',
                'content_source',
                'lock_version',
                'status',
                'scheduled_for',
                'tags',
                'featured_image_alt',
            ]);
        });
    }
};
