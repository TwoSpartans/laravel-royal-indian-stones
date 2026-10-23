<?php

namespace App\Services\Portal;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Carbon;

class LeaveDays
{
    public function count(User $user, string $startsOn, string $endsOn): float
    {
        $user->loadMissing('profile.shift', 'profile.location');
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();
        if ($end->lt($start)) {
            return 0;
        }

        $offs = $user->profile?->shift?->weeklyOffDays() ?? [0];
        $locationId = $user->profile?->work_location_id;
        $holidays = Holiday::query()
            ->whereDate('holiday_on', '>=', $start->toDateString())
            ->whereDate('holiday_on', '<=', $end->toDateString())
            ->where(function ($query) use ($locationId): void {
                $query->whereNull('work_location_id');
                if ($locationId !== null) {
                    $query->orWhere('work_location_id', $locationId);
                }
            })
            ->pluck('holiday_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $days = 0;
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if (in_array($cursor->dayOfWeek, $offs, true) || in_array($cursor->toDateString(), $holidays, true)) {
                continue;
            }
            $days++;
        }

        return (float) $days;
    }
}
