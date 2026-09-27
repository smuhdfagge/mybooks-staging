<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::updateOrCreate(
            ['code' => 'NG'],
            [
                'name' => 'Nigeria',
                'phone_code' => '+234',
                'currency' => 'NGN',
                'currency_symbol' => '₦',
            ]
        );
    }
}
