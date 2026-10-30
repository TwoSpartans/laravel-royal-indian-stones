<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('publication');
            $table->string('contact_name')->nullable();
            $table->string('offered_url')->nullable();
            $table->string('status')->default('to_contact');
            $table->string('published_url')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_mentions');
    }
};
