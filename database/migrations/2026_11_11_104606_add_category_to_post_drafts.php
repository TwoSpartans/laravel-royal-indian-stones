<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_drafts', function (Blueprint $table) {
            $table->foreignId('post_category_id')->nullable()->constrained('post_categories')->nullOnDelete();
        });

        DB::table('post_drafts')->orderBy('id')->lazyById()->each(function (object $draft): void {
            $categoryId = DB::table('posts')->where('id', $draft->post_id)->value('post_category_id');
            if ($categoryId !== null) {
                DB::table('post_drafts')->where('id', $draft->id)->update([
                    'post_category_id' => $categoryId,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_category_id');
        });
    }
};
