<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class InitialSeeder extends Seeder
{
    public function run(): void
    {
        // 👤 PLATFORM ADMIN
        DB::table('users')->insert([
            'id' => Str::uuid(),
            'name' => 'Platform Admin',
            'email' => 'admin@finbridge.com',
            'phone' => '01700000000',
            'password' => bcrypt('password'), // required because we are not using model
            'role' => 'platform_admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscription_plans')->truncate();

        DB::table('subscription_plans')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'trial',
                'price_bdt' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'pro',
                'price_bdt' => 999,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
