<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->text('working_on')->nullable();
            $table->text('completed_work')->nullable();
            $table->text('planned_work')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropColumn(['working_on', 'completed_work', 'planned_work']);
        });
    }
};
