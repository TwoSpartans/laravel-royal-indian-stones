<?php

namespace App\Services\Portal;

use App\Models\AttendancePunch;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\PortalTime;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceBoard
{
    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{user: User, state: string, label: string, late_minutes: int, first_in: ?AttendancePunch, last_out: ?AttendancePunch}>
     */
    public function roster(Collection $users, Carbon $day): Collection
    {
        $users = $users->filter(fn (User $user): bool => $user->recordsOwnAttendance())->values();
        if ($users->isEmpty()) {
            return collect();
        }

        if (! $users instanceof EloquentCollection) {
            $users = new EloquentCollection($users->all());
        }
        $users->loadMissing('profile.location', 'profile.shift', 'profile.department');
        $ids = $users->modelKeys();
        $punches = AttendancePunch::query()
            ->whereIn('user_id', $ids)
            ->whereNull('superseded_at')
            ->where('punched_at', '>=', $day->copy()->subDay()->startOfDay())
            ->where('punched_at', '<=', $day->copy()->addDay()->endOfDay())
            ->orderBy('punched_at')
            ->get()
            ->groupBy('user_id');

        $onLeave = LeaveRequest::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->toDateString())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $holidays = Holiday::query()->whereDate('holiday_on', $day->toDateString())->get();

        return $users->map(function (User $user) use ($day, $punches, $onLeave, $holidays): array {
            $timezone = PortalTime::timezone($user);
            $localDay = $day->copy()->timezone($timezone)->toDateString();
            $own = $punches->get($user->id, collect())->filter(
                fn (AttendancePunch $punch) => $punch->punched_at->copy()->timezone($timezone)->toDateString() === $localDay,
            );

            return $this->summarize($user, $localDay, $own->values(), in_array($user->id, $onLeave, true), $this->isHoliday($user, $holidays));
        })->values();
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     * @return array{user: User, state: string, label: string, late_minutes: int, first_in: ?AttendancePunch, last_out: ?AttendancePunch}
     */
    public function summarize(User $user, string $localDay, Collection $punches, bool $onLeave, bool $holiday): array
    {
        $user->loadMissing('profile.shift');
        $timezone = PortalTime::timezone($user);
        $shift = $user->profile?->shift;
        $offs = $shift?->weeklyOffDays() ?? [0];
        $starts = $this->at($localDay, $shift?->starts_at ?? '09:30:00', $timezone);
        $ends = $this->at($localDay, $shift?->ends_at ?? '18:30:00', $timezone);
        $grace = (int) ($shift->grace_minutes ?? 10);
        $now = now()->timezone($timezone);
        $ins = $punches->where('punch_type', 'in')->sortBy('punched_at')->values();
        $outs = $punches->where('punch_type', 'out')->sortBy('punched_at')->values();
        $firstIn = $ins->first();
        $lastOut = $outs->last();

        $state = 'absent';
        $label = 'Absent';
        $lateMinutes = 0;

        if ($holiday) {
            $state = 'holiday';
            $label = 'Holiday';
        } elseif (in_array(Carbon::parse($localDay)->dayOfWeek, $offs, true)) {
            $state = 'weekly_off';
            $label = 'Weekly off';
        } elseif ($onLeave) {
            $state = 'leave';
            $label = 'On leave';
        } elseif ($firstIn === null && $lastOut === null) {
            if ($now->toDateString() === $localDay && $now->lt($ends)) {
                $state = 'not_in_yet';
                $label = 'Not in yet';
            }
        } elseif ($firstIn === null) {
            $state = 'missed_punch';
            $label = 'Missed punch';
        } else {
            $localIn = $firstIn->punched_at->copy()->timezone($timezone);
            $lateMinutes = (int) max(0, floor(($localIn->getTimestamp() - $starts->getTimestamp()) / 60));
            $isLate = $localIn->greaterThan($starts->copy()->addMinutes($grace));
            $missedOut = $lastOut === null && ($now->toDateString() > $localDay || $now->gt($ends->copy()->addHours(2)));
            if ($missedOut) {
                $state = 'missed_punch';
                $label = 'Missed punch';
            } elseif ($isLate) {
                $state = 'late';
                $label = 'Late';
            } else {
                $state = 'present';
                $label = 'Present';
            }
        }

        return [
            'user' => $user,
            'state' => $state,
            'label' => $label,
            'late_minutes' => $lateMinutes,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
        ];
    }

    public function lastPunch(User $user): ?AttendancePunch
    {
        return AttendancePunch::query()
            ->where('user_id', $user->id)
            ->whereNull('superseded_at')
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->first();
    }

    public function workedMinutes(User $user, Carbon $from, Carbon $to): int
    {
        $end = $to->copy();
        if ($end->greaterThan(now())) {
            $end = now();
        }
        if ($end->lessThanOrEqualTo($from)) {
            return 0;
        }

        $punches = AttendancePunch::query()
            ->where('user_id', $user->id)
            ->whereNull('superseded_at')
            ->where('punched_at', '<=', $end)
            ->orderBy('punched_at')
            ->orderBy('id')
            ->get();

        $minutes = 0;
        $openAt = null;
        foreach ($punches as $punch) {
            if (in_array($punch->punch_type, ['in', 'break_end'], true)) {
                $openAt = $punch->punched_at->copy();

                continue;
            }
            if ($openAt !== null && in_array($punch->punch_type, ['out', 'break_start'], true)) {
                $minutes += $this->overlapMinutes($openAt, $punch->punched_at, $from, $end);
                $openAt = null;
            }
        }

        return $minutes;
    }

    /**
     * @return array{today: int, month: int, unsettled: list<array{date: string, started_at: ?Carbon, minutes: int}>}
     */
    public function hourSummary(User $user, Carbon $now): array
    {
        $timezone = PortalTime::timezone($user);
        $localNow = $now->copy()->timezone($timezone);
        $monthStart = $localNow->copy()->startOfMonth();
        $punches = AttendancePunch::query()
            ->where('user_id', $user->id)
            ->whereNull('superseded_at')
            ->where('punched_at', '>=', $monthStart)
            ->where('punched_at', '<=', $localNow)
            ->orderBy('punched_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AttendancePunch $punch): string => $punch->punched_at->copy()->timezone($timezone)->toDateString());

        $settledMonth = 0;
        $settledToday = 0;
        $unsettled = [];
        for ($day = $monthStart->copy(); $day->lte($localNow); $day->addDay()) {
            $key = $day->toDateString();
            $dayPunches = $punches->get($key, collect());
            if ($dayPunches->isEmpty()) {
                continue;
            }

            $cap = $day->isSameDay($localNow) ? $localNow->copy() : $day->copy()->addDay()->startOfDay();
            $worked = $this->dayMinutes($dayPunches, $day->copy()->startOfDay(), $cap);
            if ($worked['closed']) {
                $settledMonth += $worked['minutes'];
                if ($day->isSameDay($localNow)) {
                    $settledToday = $worked['minutes'];
                }

                continue;
            }

            $unsettled[] = [
                'date' => $key,
                'started_at' => $worked['started_at'],
                'minutes' => $worked['minutes'],
            ];
        }

        return [
            'today' => $settledToday,
            'month' => $settledMonth,
            'unsettled' => $unsettled,
        ];
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     * @return array{minutes: int, closed: bool, started_at: ?Carbon}
     */
    private function dayMinutes(Collection $punches, Carbon $from, Carbon $cap): array
    {
        $minutes = 0;
        $openAt = null;
        $startedAt = null;
        $lastType = null;
        foreach ($punches as $punch) {
            $lastType = $punch->punch_type;
            if ($punch->punch_type === 'in' && $startedAt === null) {
                $startedAt = $punch->punched_at->copy();
            }
            if (in_array($punch->punch_type, ['in', 'break_end'], true)) {
                $openAt = $punch->punched_at->copy();

                continue;
            }
            if ($openAt !== null && in_array($punch->punch_type, ['out', 'break_start'], true)) {
                $minutes += $this->overlapMinutes($openAt, $punch->punched_at, $from, $cap);
                $openAt = null;
            }
        }
        if ($openAt !== null) {
            $minutes += $this->overlapMinutes($openAt, $cap, $from, $cap);
        }

        return [
            'minutes' => $minutes,
            'closed' => $lastType === 'out',
            'started_at' => $startedAt,
        ];
    }

    private function overlapMinutes(Carbon $open, Carbon $close, Carbon $from, Carbon $to): int
    {
        $start = $open->greaterThan($from) ? $open : $from;
        $finish = $close->lessThan($to) ? $close : $to;
        if ($finish->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) floor(($finish->getTimestamp() - $start->getTimestamp()) / 60);
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     */
    private function isHoliday(User $user, Collection $holidays): bool
    {
        $locationId = $user->profile?->work_location_id;

        return $holidays->contains(function (Holiday $holiday) use ($locationId): bool {
            return $holiday->work_location_id === null || $holiday->work_location_id === $locationId;
        });
    }

    private function at(string $date, mixed $time, string $timezone): Carbon
    {
        $clock = substr((string) $time, 0, 8);

        return Carbon::parse($date.' '.$clock, $timezone);
    }
}
