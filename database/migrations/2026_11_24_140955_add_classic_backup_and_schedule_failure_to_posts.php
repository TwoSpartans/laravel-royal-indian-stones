<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->longText('classic_body_backup')->nullable()->after('body');
            $table->text('schedule_failure_reason')->nullable()->after('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['classic_body_backup', 'schedule_failure_reason']);
        });
    }
};
