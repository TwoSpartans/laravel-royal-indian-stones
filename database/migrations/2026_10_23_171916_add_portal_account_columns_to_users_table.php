<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_site_admin')->default(true);
            $table->string('portal_role')->nullable()->index();
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->timestamp('password_set_at')->nullable();
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_site_admin',
                'portal_role',
                'deactivated_at',
                'password_set_at',
                'mfa_secret',
                'mfa_recovery_codes',
                'mfa_confirmed_at',
                'timezone',
            ]);
        });
    }
};
