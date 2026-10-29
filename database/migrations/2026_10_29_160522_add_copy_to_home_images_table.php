<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_images', function (Blueprint $table) {
            $table->string('eyebrow')->nullable()->after('alt');
            $table->string('title')->nullable()->after('eyebrow');
            $table->string('subtitle')->nullable()->after('title');
            $table->text('body')->nullable()->after('subtitle');
            $table->string('button_label')->nullable()->after('body');
            $table->string('link')->nullable()->after('button_label');
        });
    }

    public function down(): void
    {
        Schema::table('home_images', function (Blueprint $table) {
            $table->dropColumn(['eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link']);
        });
    }
};
