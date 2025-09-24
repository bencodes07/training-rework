<?php

namespace Database\Seeders;

use App\Models\Tier2Endorsement;
use Illuminate\Database\Seeder;

class Tier2EndorsementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tier2Endorsements = [
            [
                'name' => 'AFIS Tower Endorsement',
                'position' => 'EDXX_AFIS',
                'moodle_course_id' => 123, // Replace with actual Moodle course ID
            ],
            [
                'name' => 'Special Procedures',
                'position' => 'EDXX_SPECIAL',
                'moodle_course_id' => 124, // Replace with actual Moodle course ID
            ],
            // Add more tier 2 endorsements as needed
        ];

        foreach ($tier2Endorsements as $endorsement) {
            Tier2Endorsement::firstOrCreate(
                ['position' => $endorsement['position']],
                $endorsement
            );
        }

        $this->command->info('Tier 2 endorsements seeded successfully.');
    }
}