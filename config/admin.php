<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site administrator
    |--------------------------------------------------------------------------
    |
    | Used by the content import and database seeder to create the admin
    | account. Read through config() so `php artisan config:cache` still works.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
