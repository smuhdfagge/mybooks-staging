<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminUser::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@mybooks.local')],
            [
                'name' => 'System Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD', Str::random(32))),
                'is_active' => true,
            ]
        );
    }
}
