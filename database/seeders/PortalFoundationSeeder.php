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

        foreach ([
            ['Casual Leave', 'casual', true, 8, false, 0],
            ['Sick Leave', 'sick', true, 8, false, 0],
            ['Earned Leave', 'earned', true, 15, true, 3],
            ['Unpaid Leave', 'unpaid', false, 0, false, 0],
        ] as [$name, $code, $paid, $allowance, $needsHr, $threshold]) {
            LeaveType::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_paid' => $paid,
                    'annual_allowance' => $allowance,
                    'needs_hr_approval' => $needsHr,
                    'hr_threshold_days' => $threshold,
                    'is_active' => true,
                ],
            );
        }

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
