<?php

namespace Database\Seeders;

use App\Support\AdminAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AdminAccount::sync();
        $this->call(PortalFoundationSeeder::class);
    }
}
