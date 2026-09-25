<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Seed India's national holidays for the year requested by the client.
     */
    public function run(): void
    {
        $holidays = [
            [
                'date' => '2025-01-26',
                'name' => 'Republic Day',
                'type' => 'National',
                'description' => 'National holiday commemorating the Constitution of India.',
            ],
            [
                'date' => '2025-08-15',
                'name' => 'Independence Day',
                'type' => 'National',
                'description' => 'National holiday commemorating India\'s independence.',
            ],
            [
                'date' => '2025-10-02',
                'name' => 'Gandhi Jayanti',
                'type' => 'National',
                'description' => 'National holiday marking the birth anniversary of Mahatma Gandhi.',
            ],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                ['date' => $holiday['date'], 'name' => $holiday['name']],
                $holiday,
            );
        }
    }
}
