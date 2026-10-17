<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')
            ->where('slug', 'blog-the-tranquil-beauty-and-creative-applications of-natural-pebbles-240204')
            ->update(['slug' => 'blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204']);
    }

    public function down(): void
    {
        DB::table('posts')
            ->where('slug', 'blog-the-tranquil-beauty-and-creative-applications-of-natural-pebbles-240204')
            ->update(['slug' => 'blog-the-tranquil-beauty-and-creative-applications of-natural-pebbles-240204']);
    }
};
