<?php

namespace App\Services\Portal;

use App\Models\AttendancePunch;
use App\Models\User;
use App\Support\PortalTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceReview
{
    public function __construct(private AttendanceBoard $board) {}

    /**
     * @param  Collection<int, User>  $people
     * @return array{roster: Collection<int, array<string, mixed>>, counts: Collection<string, int>}
     */
    public function day(Collection $people, Carbon $day): array
    {
        $roster = $this->board->roster($people, $day);

        return [
            'roster' => $roster,
            'counts' => $roster->countBy('state'),
        ];
    }

    /**
     * @param  Collection<int, User>  $people
     * @return Collection<int, AttendancePunch>
     */
    public function dayEvents(Collection $people, Carbon $day): Collection
    {
        if ($people->isEmpty()) {
            return collect();
        }

        $start = $day->copy()->subDay()->startOfDay();
        $end = $day->copy()->addDay()->endOfDay();

        return AttendancePunch::query()
            ->with('user')
            ->whereIn('user_id', $people->modelKeys())
            ->whereNull('superseded_at')
            ->where('punched_at', '>=', $start)
            ->where('punched_at', '<=', $end)
            ->orderByDesc('punched_at')
            ->get()
            ->filter(function (AttendancePunch $punch) use ($day): bool {
                $timezone = $punch->timezone ?: PortalTime::timezone($punch->user);

                return $punch->punched_at->copy()->timezone($timezone)->toDateString() === $day->copy()->timezone($timezone)->toDateString();
            })
            ->take(8)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function period(User $person, Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $row = $this->board->roster(collect([$person]), $day->copy())->first();
            if (! is_array($row)) {
                continue;
            }
            $scheduled = $this->scheduledMinutes($person);
            $off = in_array($row['state'], ['holiday', 'weekly_off'], true);
            $days[] = [
                'date' => $day->toDateString(),
                'state' => $row['state'],
                'status' => $row['state'] === 'late' && $row['late_minutes'] > 0
                    ? $row['label'].', '.$row['late_minutes'].' min late'
                    : $row['label'],
                'in' => $this->clock($row['first_in']),
                'out' => $this->clock($row['last_out']),
                'hours' => $row['hours'] === null ? '' : ($row['closed'] ? $row['hours'] : 'Unsettled'),
                'minutes' => $row['minutes'],
                'closed' => $row['closed'],
                'late_minutes' => $row['late_minutes'],
                'scheduled_minutes' => $off || $row['state'] === 'leave' ? 0 : $scheduled,
            ];
        }

        return $this->summarize($person, $from, $to, $days);
    }

    /**
     * @param  Collection<int, User>  $people
     * @return list<array<string, mixed>>
     */
    public function compare(Collection $people, Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach ($people as $person) {
            $rows[] = $this->period($person, $from, $to);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function summarize(User $person, Carbon $from, Carbon $to, array $days): array
    {
        $present = 0;
        $late = 0;
        $missed = 0;
        $absent = 0;
        $leave = 0;
        $scheduled = 0;
        $tracked = 0;
        $closedDays = 0;
        $unsettled = 0;
        foreach ($days as $day) {
            $present += $day['state'] === 'present' ? 1 : 0;
            $late += $day['state'] === 'late' ? 1 : 0;
            $missed += $day['state'] === 'missed_punch' ? 1 : 0;
            $absent += $day['state'] === 'absent' ? 1 : 0;
            $leave += $day['state'] === 'leave' ? 1 : 0;
            if (! in_array($day['state'], ['holiday', 'weekly_off', 'leave', 'not_in_yet'], true)) {
                $scheduled++;
            }
            if ($day['closed'] === true && is_int($day['minutes'])) {
                $tracked += $day['minutes'];
                $closedDays++;
            }
            if ($day['closed'] === false && $day['minutes'] !== null) {
                $unsettled++;
            }
        }

        $rate = $scheduled > 0 ? round((($present + $late) / $scheduled) * 100, 1) : null;

        return [
            'user' => $person,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scheduled' => $scheduled,
            'worked' => $present + $late + $missed,
            'present' => $present,
            'late' => $late,
            'missed' => $missed,
            'absent' => $absent,
            'leave' => $leave,
            'rate' => $rate,
            'tracked_minutes' => $tracked,
            'tracked_label' => $this->board->formatMinutes($tracked),
            'average_label' => $closedDays > 0 ? $this->board->formatMinutes((int) round($tracked / $closedDays)) : '0h 00m',
            'unsettled' => $unsettled,
            'days' => $days,
        ];
    }

    private function scheduledMinutes(User $person): int
    {
        $person->loadMissing('profile.shift');
        $shift = $person->profile?->shift;
        $start = $this->minutes(substr((string) ($shift?->starts_at ?? '09:30:00'), 0, 8));
        $end = $this->minutes(substr((string) ($shift?->ends_at ?? '18:30:00'), 0, 8));
        if ($end <= $start) {
            $end += 24 * 60;
        }

        return max(0, $end - $start);
    }

    private function minutes(string $clock): int
    {
        $parts = explode(':', $clock);

        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }

    private function clock(?AttendancePunch $punch): string
    {
        if ($punch?->punched_at === null) {
            return '';
        }

        $timezone = $punch->timezone ?: 'Asia/Kolkata';

        return $punch->punched_at->copy()->timezone($timezone)->format('H:i');
    }
}
