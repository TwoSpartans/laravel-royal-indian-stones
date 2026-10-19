<?php

namespace App\Support;

use App\Models\User;

class AdminAccount
{
    public static function sync(): bool
    {
        $email = config('admin.email');
        $password = config('admin.password');
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
