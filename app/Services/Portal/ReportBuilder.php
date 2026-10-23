<?php

namespace App\Services\Portal;

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
        $rows = [];
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            foreach ($this->board->roster($people, $day->copy()) as $row) {
                $rows[] = [
                    $row['user']->name,
                    $day->toDateString(),
                    $row['label'],
                    (string) $row['late_minutes'],
                    $row['first_in']?->punched_at?->timezone('Asia/Kolkata')->format('H:i') ?? '',
                    $row['last_out']?->punched_at?->timezone('Asia/Kolkata')->format('H:i') ?? '',
                ];
            }
        }

        return [
            'title' => 'Attendance '.$from.' to '.$to,
            'headings' => ['Employee', 'Date', 'Status', 'Minutes after shift start', 'First in', 'Last out'],
            'rows' => $rows,
        ];
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
