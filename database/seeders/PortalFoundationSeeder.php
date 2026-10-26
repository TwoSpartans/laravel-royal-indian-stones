<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class PortalFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Sales',
            'Sourcing',
            'Processing',
            'Quality',
            'Packaging',
            'Dispatch',
            'Export documentation',
            'Administration',
        ] as $name) {
            Department::query()->firstOrCreate(['name' => $name]);
        }

        foreach ([
            ['Jodhpur Head Office', 'office'],
            ['Processing Yard', 'factory'],
            ['Dispatch Warehouse', 'warehouse'],
        ] as [$name, $kind]) {
            WorkLocation::query()->firstOrCreate(
                ['name' => $name],
                ['kind' => $kind, 'timezone' => 'Asia/Kolkata'],
            );
        }

        Shift::query()->firstOrCreate(
            ['name' => 'General'],
            [
                'starts_at' => '09:30:00',
                'ends_at' => '18:30:00',
                'grace_minutes' => 10,
                'weekly_off' => [0],
            ],
        );

        LeaveType::query()->firstOrCreate(
            ['code' => 'leave'],
            [
                'name' => 'Leave',
                'is_paid' => true,
                'annual_allowance' => 31,
                'needs_hr_approval' => true,
                'hr_threshold_days' => 3,
                'is_active' => true,
            ],
        );

        foreach ([
            ['Republic Day', '2026-01-26'],
            ['Holi', '2026-03-04'],
            ['Independence Day', '2026-08-15'],
            ['Gandhi Jayanti', '2026-10-02'],
            ['Diwali', '2026-11-08'],
        ] as [$name, $date]) {
            Holiday::query()->firstOrCreate(
                ['name' => $name, 'holiday_on' => $date],
                ['work_location_id' => null],
            );
        }
    }
}
