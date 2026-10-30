<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EmploymentStatus;
use App\Enums\PortalRole;
use App\Models\AccessEvent;
use App\Models\AttendanceCorrection;
use App\Models\EmployeeDocument;
use App\Models\ExpenseClaim;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Payslip;
use App\Models\RegisteredDevice;
use App\Models\RemoteWorkAuthorization;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkTask;
use App\Services\Portal\AttendanceBoard;
use App\Support\PortalTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends PortalController
{
    public function __invoke(Request $request, AttendanceBoard $board): View
    {
        $actor = $this->actor($request);
        $actor->load('profile.location', 'profile.shift', 'profile.department');
        $today = now()->timezone('Asia/Kolkata')->startOfDay();
        $reportDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString(), 'Asia/Kolkata')->startOfDay()
            : $today->copy();
        $locationId = $request->integer('location_id');
        $people = $this->access->peopleQuery($actor)
            ->whereNull('deactivated_at')
            ->whereNotNull('password_set_at')
            ->whereHas('profile', function ($query): void {
                $query->whereIn('employment_status', [
                    EmploymentStatus::Active->value,
                    EmploymentStatus::Probation->value,
                ]);
            })
            ->with('profile.location', 'profile.shift', 'profile.department')
            ->get()
            ->filter(fn (User $user): bool => $user->recordsOwnAttendance())
            ->when($locationId > 0, fn ($rows) => $rows->filter(fn (User $user): bool => $user->profile?->work_location_id === $locationId))
            ->values();

        $roster = $board->roster($people, $reportDate);
        $counts = [
            'workforce' => $people->count(),
            'on_time' => $roster->where('state', 'present')->count(),
            'present' => $roster->whereIn('state', ['present', 'late'])->count(),
            'late' => $roster->where('state', 'late')->count(),
            'absent' => $roster->where('state', 'absent')->count(),
            'not_in_yet' => $roster->where('state', 'not_in_yet')->count(),
            'leave' => $roster->where('state', 'leave')->count(),
            'missed_punch' => $roster->where('state', 'missed_punch')->count(),
        ];

        $locations = $roster->groupBy(fn (array $row) => $row['user']->profile?->location?->name ?? 'Unassigned')
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'people' => $rows->count(),
                'present' => $rows->whereIn('state', ['present', 'late'])->count(),
                'not_in_yet' => $rows->where('state', 'not_in_yet')->count(),
                'absent' => $rows->where('state', 'absent')->count(),
            ])->values();

        $self = $board->roster(collect([$actor]), $today)->first();
        $ids = $this->access->reportUserIds($actor);
        $scope = function ($query) use ($ids) {
            if ($ids !== null) {
                $query->whereIn('user_id', $ids === [] ? [-1] : $ids);
            }

            return $query;
        };

        $upcomingLeave = $scope(LeaveRequest::query()
            ->with('user', 'type'))
            ->where('status', 'approved')
            ->whereDate('starts_on', '>=', $today->toDateString())
            ->whereDate('starts_on', '<=', $today->copy()->addDays(14)->toDateString())
            ->orderBy('starts_on')
            ->limit(8)
            ->get();

        $expiringDocuments = $scope(EmployeeDocument::query()
            ->with('user'))
            ->whereDate('expires_on', '>=', $today->toDateString())
            ->whereDate('expires_on', '<=', $today->copy()->addDays(30)->toDateString())
            ->orderBy('expires_on')
            ->limit(8)
            ->get();

        $approvals = [
            'leave' => $scope(LeaveRequest::query())->whereIn('status', $this->leaveStatuses($actor))->count(),
            'expenses' => $scope(ExpenseClaim::query())->whereIn('status', $this->expenseStatuses($actor))->count(),
            'corrections' => $scope(AttendanceCorrection::query())->where('status', 'pending')->count(),
            'overtime' => $scope(OvertimeRequest::query())->where('status', 'pending')->count(),
            'devices' => $this->access->canAdminister($actor) ? RegisteredDevice::query()->where('status', 'pending')->count() : 0,
            'remote' => $this->access->canAdminister($actor) ? RemoteWorkAuthorization::query()->where('status', 'pending')->count() : 0,
            'reviews' => $this->access->canAdminister($actor)
                ? AccessEvent::query()->whereIn('classification', ['outside_network', 'unable_to_verify'])->whereNull('reviewed_at')->count()
                : 0,
        ];

        $payslips = $scope(Payslip::query())->select('status')->get();
        $tasks = WorkTask::query()
            ->with('assignee')
            ->where(function ($query) use ($ids): void {
                if ($ids === null) {
                    return;
                }
                $query->whereIn('assignee_id', $ids === [] ? [-1] : $ids);
            })
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('due_on')
            ->limit(6)
            ->get();

        $workedHours = null;
        if ($actor->portal_role === PortalRole::Employee || $actor->portal_role?->isManagerLevel()) {
            $summary = $board->hourSummary($actor, now());
            $workedHours = [
                'today' => $this->formatWorked($summary['today']),
                'month' => $this->formatWorked($summary['month']),
                'unsettled_total' => $this->formatWorked(array_sum(array_column($summary['unsettled'], 'minutes'))),
                'unsettled' => array_map(fn (array $day): array => [
                    'date' => Carbon::parse($day['date'])->format('d M Y'),
                    'started' => $day['started_at'] instanceof Carbon
                        ? $day['started_at']->timezone(PortalTime::timezone($actor))->format('H:i')
                        : '—',
                    'hours' => $this->formatWorked($day['minutes']),
                ], $summary['unsettled']),
            ];
        }

        return view('portal.dashboard', [
            'counts' => $counts,
            'locations' => $locations,
            'places' => WorkLocation::query()->orderBy('name')->get(),
            'reportDate' => $reportDate->toDateString(),
            'locationId' => $locationId,
            'self' => $self,
            'lastPunch' => $board->lastPunch($actor),
            'upcomingLeave' => $upcomingLeave,
            'expiringDocuments' => $expiringDocuments,
            'approvals' => $approvals,
            'payslipCounts' => $payslips->countBy('status'),
            'tasks' => $tasks,
            'balance' => $actor->profile ? LeaveBalance::query()->with('type')->where('user_id', $actor->id)->where('year', (int) $today->year)->whereHas('type', fn ($query) => $query->where('is_active', true))->first() : null,
            'workedHours' => $workedHours,
        ]);
    }

    private function formatWorked(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    /**
     * @return list<string>
     */
    private function leaveStatuses(User $actor): array
    {
        if ($this->access->canAdminister($actor)) {
            return ['pending_manager', 'pending_hr'];
        }

        if ($actor->portal_role?->isManagerLevel()) {
            return ['pending_manager'];
        }

        return ['pending_manager', 'pending_hr'];
    }

    /**
     * @return list<string>
     */
    private function expenseStatuses(User $actor): array
    {
        if ($this->access->canAdminister($actor)) {
            return ['submitted', 'pending_hr'];
        }

        if ($actor->portal_role?->isManagerLevel()) {
            return ['submitted'];
        }

        return ['submitted', 'pending_hr'];
    }
}
