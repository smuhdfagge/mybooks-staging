<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nigeria = Country::where('code', 'NG')->first();

        if (!$nigeria) {
            $this->command->warn('Nigeria country not found. Please run CountrySeeder first.');
            return;
        }

        $states = [
            'Abia',
            'Adamawa',
            'Akwa Ibom',
            'Anambra',
            'Bauchi',
            'Bayelsa',
            'Benue',
            'Borno',
            'Cross River',
            'Delta',
            'Ebonyi',
            'Edo',
            'Ekiti',
            'Enugu',
            'FCT Abuja',
            'Gombe',
            'Imo',
            'Jigawa',
            'Kaduna',
            'Kano',
            'Katsina',
            'Kebbi',
            'Kogi',
            'Kwara',
            'Lagos',
            'Nasarawa',
            'Niger',
            'Ogun',
            'Ondo',
            'Osun',
            'Oyo',
            'Plateau',
            'Rivers',
            'Sokoto',
            'Taraba',
            'Yobe',
            'Zamfara',
        ];

        // Rename 'Abuja FCT' to 'FCT Abuja' if it exists
        State::where('name', 'Abuja FCT')
            ->where('country_id', $nigeria->id)
            ->update(['name' => 'FCT Abuja']);

        foreach ($states as $state) {
            State::updateOrCreate(
                [
                    'name' => $state,
                    'country_id' => $nigeria->id,
                ]
            );
        }
    }
}
