<?php

namespace App\Services\Portal;

use App\Models\AttendancePunch;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkTask;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportBuilder
{
    public function __construct(
        private Access $access,
        private AttendanceBoard $board,
    ) {}

    /**
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    public function build(User $actor, string $type, string $from, string $to): array
    {
        $ids = $this->access->reportUserIds($actor);
        $people = User::query()
            ->whereNotNull('portal_role')
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->with('profile.department', 'profile.location')
            ->orderBy('name')
            ->get();

        return match ($type) {
            'attendance' => $this->attendance($people, $from, $to),
            'leave' => $this->leave($people, $from, $to),
            'overtime' => $this->overtime($people, $from, $to),
            'reimbursements' => $this->reimbursements($people, $from, $to),
            'headcount' => $this->headcount($people),
            'tasks' => $this->tasks($people, $from, $to),
            default => ['title' => 'Report', 'headings' => [], 'rows' => []],
        };
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function attendance($people, string $from, string $to): array
    {
        $lines = [];
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            foreach ($this->board->roster($people, $day->copy()) as $row) {
                $lines[] = [
                    'id' => $row['user']->id,
                    'name' => $row['user']->name,
                    'date' => $day->toDateString(),
                    'state' => $row['state'],
                    'status' => $this->attendanceStatus($row),
                    'in' => $this->clock($row['first_in']),
                    'out' => $this->clock($row['last_out']),
                    'hours' => $this->attendanceHours($row),
                    'minutes' => $row['minutes'],
                    'closed' => $row['closed'],
                ];
            }
        }

        $first = null;
        foreach ($lines as $line) {
            if ($line['in'] !== '' || $line['out'] !== '') {
                $first = $first === null || $line['date'] < $first ? $line['date'] : $first;
            }
        }

        $note = 'No clock-ins in this range.';
        if ($first !== null) {
            $lines = array_values(array_filter($lines, fn (array $line): bool => $line['date'] >= $first));
            $note = $first > $from ? 'No clock-ins before '.$first.'. Earlier dates are left out.' : '';
        }

        $summary = [];
        foreach (collect($lines)->groupBy('id') as $personLines) {
            $settled = 0;
            $unsettled = 0;
            $present = 0;
            $late = 0;
            $missed = 0;
            $absent = 0;
            foreach ($personLines as $line) {
                $present += $line['state'] === 'present' ? 1 : 0;
                $late += $line['state'] === 'late' ? 1 : 0;
                $missed += $line['state'] === 'missed_punch' ? 1 : 0;
                $absent += $line['state'] === 'absent' ? 1 : 0;
                if ($line['closed'] === true && is_int($line['minutes'])) {
                    $settled += $line['minutes'];
                }
                if ($line['closed'] === false && $line['minutes'] !== null) {
                    $unsettled++;
                }
            }
            $summary[] = [
                $personLines->first()['name'],
                (string) $present,
                (string) $late,
                (string) $missed,
                (string) $absent,
                $this->board->formatMinutes($settled),
                (string) $unsettled,
            ];
        }

        $daily = array_map(fn (array $line): array => [
            $line['name'],
            $line['date'],
            $line['status'],
            $line['in'],
            $line['out'],
            $line['hours'],
        ], $first === null ? [] : $lines);

        $summaryHeadings = ['Employee', 'Present', 'Late', 'Missed clock-out', 'Absent', 'Hours worked', 'Unsettled days'];
        $dailyHeadings = ['Employee', 'Date', 'Status', 'First in', 'Last out', 'Hours'];

        return [
            'title' => 'Attendance '.$from.' to '.$to,
            'note' => $note,
            'headings' => $dailyHeadings,
            'rows' => $daily,
            'sections' => [
                ['title' => 'Summary', 'headings' => $summaryHeadings, 'rows' => $summary],
                ['title' => 'Each day', 'headings' => $dailyHeadings, 'rows' => $daily],
            ],
        ];
    }

    /**
     * @param  array{state: string, label: string, late_minutes: int}  $row
     */
    private function attendanceStatus(array $row): string
    {
        if ($row['state'] === 'late' && $row['late_minutes'] > 0) {
            return $row['label'].', '.$row['late_minutes'].' min late';
        }

        return $row['label'];
    }

    /**
     * @param  array{hours: ?string, closed: ?bool}  $row
     */
    private function attendanceHours(array $row): string
    {
        if ($row['hours'] === null) {
            return '';
        }

        return $row['closed'] ? $row['hours'] : 'Unsettled';
    }

    private function clock(?AttendancePunch $punch): string
    {
        if ($punch?->punched_at === null) {
            return '';
        }

        return $punch->punched_at->copy()->timezone($punch->timezone ?: 'Asia/Kolkata')->format('H:i');
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function leave($people, string $from, string $to): array
    {
        $rows = LeaveRequest::query()
            ->with('user', 'type')
            ->whereIn('user_id', $people->modelKeys())
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->orderBy('starts_on')
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                $leave->user?->name ?? '',
                $leave->type?->name ?? '',
                $leave->starts_on->toDateString(),
                $leave->ends_on->toDateString(),
                (string) $leave->days,
                $leave->status,
            ])->all();

        return [
            'title' => 'Leave '.$from.' to '.$to,
            'headings' => ['Employee', 'Type', 'Starts', 'Ends', 'Days', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function overtime($people, string $from, string $to): array
    {
        $rows = OvertimeRequest::query()
            ->with('user')
            ->whereIn('user_id', $people->modelKeys())
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->orderBy('work_date')
            ->get()
            ->map(fn (OvertimeRequest $request) => [
                $request->user?->name ?? '',
                $request->work_date->toDateString(),
                (string) $request->minutes,
                $request->status,
            ])->all();

        return [
            'title' => 'Overtime '.$from.' to '.$to,
            'headings' => ['Employee', 'Date', 'Minutes', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function reimbursements($people, string $from, string $to): array
    {
        $rows = ExpenseClaim::query()
            ->with('user')
            ->whereIn('user_id', $people->modelKeys())
            ->whereDate('incurred_on', '>=', $from)
            ->whereDate('incurred_on', '<=', $to)
            ->orderBy('incurred_on')
            ->get()
            ->map(fn (ExpenseClaim $claim) => [
                $claim->user?->name ?? '',
                $claim->incurred_on->toDateString(),
                (string) config('portal.expense_categories.'.$claim->category, $claim->category),
                Money::format($claim->amount_paise),
                $claim->status,
                $claim->payment_state,
            ])->all();

        return [
            'title' => 'Reimbursements '.$from.' to '.$to,
            'headings' => ['Employee', 'Date', 'Category', 'Amount', 'Status', 'Payment record'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function headcount($people): array
    {
        $rows = [];
        $groups = $people->groupBy(fn (User $user) => ($user->profile?->location?->name ?? 'Unassigned').'|'.($user->profile?->department?->name ?? 'Unassigned'));
        foreach ($groups as $key => $group) {
            [$location, $department] = explode('|', $key, 2);
            $rows[] = [
                $location,
                $department,
                (string) $group->count(),
                (string) $group->filter(fn (User $user) => $user->profile?->employment_status?->value === 'probation')->count(),
            ];
        }

        return [
            'title' => 'Headcount',
            'headings' => ['Location', 'Department', 'People', 'On probation'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array{title: string, headings: list<string>, rows: list<list<string>>}
     */
    private function tasks($people, string $from, string $to): array
    {
        $rows = WorkTask::query()
            ->with('assignee', 'owner')
            ->where(function ($query) use ($people): void {
                $query->whereIn('assignee_id', $people->modelKeys())
                    ->orWhereIn('owner_id', $people->modelKeys());
            })
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('due_on', [$from, $to])->orWhereNull('due_on');
            })
            ->orderBy('due_on')
            ->get()
            ->map(fn (WorkTask $task) => [
                $task->title,
                (string) config('portal.task_categories.'.$task->category, $task->category),
                $task->assignee?->name ?? '',
                $task->owner?->name ?? '',
                $task->due_on?->toDateString() ?? '',
                $task->status,
            ])->all();

        return [
            'title' => 'Tasks '.$from.' to '.$to,
            'headings' => ['Task', 'Category', 'Assignee', 'Owner', 'Due', 'Status'],
            'rows' => $rows,
        ];
    }

    public function csvCell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
