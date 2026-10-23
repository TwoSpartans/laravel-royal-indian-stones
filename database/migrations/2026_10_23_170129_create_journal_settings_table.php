<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->default('The Journal');
            $table->string('heading_lead')->default('Stories in');
            $table->string('heading_emphasis')->default('Indian stone.');
            $table->text('introduction');
            $table->string('caption')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('cover_image_alt')->nullable();
            $table->string('edit_kicker')->default('01 / Latest');
            $table->string('edit_heading')->default('From the quarry to the building.');
            $table->json('edit_post_ids')->nullable();
            $table->string('closer_kicker')->default('02 / A closer look');
            $table->string('closer_heading')->default('Know the materials. Care for the surface.');
            $table->json('closer_post_ids')->nullable();
            $table->foreignId('maker_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('maker_eyebrow')->nullable();
            $table->string('maker_heading')->nullable();
            $table->text('maker_body')->nullable();
            $table->string('maker_label')->nullable();
            $table->string('maker_path')->nullable();
            $table->string('maker_image')->nullable();
            $table->string('maker_image_alt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_settings');
    }
};
