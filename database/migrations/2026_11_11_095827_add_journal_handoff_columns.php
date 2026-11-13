<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('title');
            $table->unsignedInteger('view_count')->default(0);
        });

        Schema::table('post_drafts', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
        });

        Schema::table('journal_settings', function (Blueprint $table) {
            $table->foreignId('closer_category_id')->nullable()->after('closer_post_ids')->constrained('post_categories')->nullOnDelete();
            $table->boolean('maker_enabled')->default(true)->after('maker_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closer_category_id');
            $table->dropColumn('maker_enabled');
        });

        Schema::table('post_drafts', function (Blueprint $table) {
            $table->dropColumn('meta_title');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'view_count']);
        });
    }
};
