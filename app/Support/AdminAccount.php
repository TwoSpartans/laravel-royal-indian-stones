<?php

namespace App\Support;

use App\Models\User;

class AdminAccount
{
    public static function sync(): bool
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return false;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => 'Site admin', 'password' => $password]
        );

        return true;
    }
}
