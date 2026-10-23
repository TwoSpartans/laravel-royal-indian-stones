<?php

use App\Models\Post;
use App\Support\ReadingTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedSmallInteger('reading_minutes')->nullable();
            $table->index(['is_published', 'status', 'published_on'], 'posts_public_listing_index');
        });

        Post::query()
            ->select(['id', 'body', 'summary', 'body_document', 'content_source'])
            ->orderBy('id')
            ->chunkById(40, function ($posts): void {
                foreach ($posts as $post) {
                    DB::table('posts')->where('id', $post->id)->update([
                        'reading_minutes' => ReadingTime::minutesFor($post),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_public_listing_index');
            $table->dropColumn('reading_minutes');
        });
    }
};
