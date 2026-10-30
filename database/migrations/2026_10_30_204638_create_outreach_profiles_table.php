<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('kind')->default('company_profile');
            $table->date('last_checked_on')->nullable();
            $table->text('gap')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_profiles');
    }
};
