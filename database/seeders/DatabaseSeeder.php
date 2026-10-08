<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Reference data only. Demo data is loaded separately with DemoSeeder. */
    public function run(): void
    {
        $this->call([AccessSeeder::class, SkillSeeder::class]);
    }
}
