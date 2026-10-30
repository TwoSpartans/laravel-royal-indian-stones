<?php

namespace App\Http\Controllers\Portal;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
use App\Models\Department;
use App\Models\OvertimeRequest;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Notifications\PortalStatusNotification;
use App\Services\Portal\AttendanceBoard;
use App\Services\Portal\AttendanceClock;
use App\Services\Portal\AttendanceReview;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\CorrectionDecision;
use App\Support\PortalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends PortalController
{
    public function index(Request $request, AttendanceBoard $board, AttendanceReview $review): View
    {
        $actor = $this->actor($request);
        $people = $this->rosterPeople($actor);
        if ($actor->portal_role?->canAdminister() === true) {
            return $this->overview($request, $review, $actor, $people);
        }

        $day = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : now();
        $selected = $people->firstWhere('id', $request->integer('user_id')) ?? $people->first();
        abort_if($selected instanceof User && ! ($this->access->canViewUser($actor, $selected) || $selected->is($actor)), 404);

        $timezone = $selected instanceof User ? PortalTime::timezone($selected) : PortalTime::timezone($actor);
        $localDay = $day->copy()->timezone($timezone)->toDateString();
        $punches = $selected instanceof User
            ? AttendancePunch::query()
                ->where('user_id', $selected->id)
                ->whereNull('superseded_at')
                ->where('punched_at', '>=', Carbon::parse($localDay, $timezone)->subDay())
                ->where('punched_at', '<', Carbon::parse($localDay, $timezone)->addDays(2))
                ->orderBy('punched_at')
                ->get()
                ->filter(fn (AttendancePunch $punch): bool => $punch->punched_at->copy()->timezone($timezone)->toDateString() === $localDay)
                ->values()
            : collect();

        $roster = $board->roster($people, $day);
        $status = $request->string('status')->toString();
        $statuses = ['present', 'late', 'not_in_yet', 'missed_punch', 'leave', 'absent'];
        if (! in_array($status, $statuses, true)) {
            $status = '';
        }

        return view('portal.attendance.index', [
            'roster' => $status === '' ? $roster : $roster->where('state', $status)->values(),
            'counts' => $roster->countBy('state'),
            'status' => $status,
            'selected' => $selected,
            'punches' => $punches,
            'lastPunch' => $board->lastPunch($actor),
            'corrections' => AttendanceCorrection::query()->with('user')->whereIn('user_id', $people->modelKeys())->latest()->limit(30)->get(),
            'overtime' => OvertimeRequest::query()->with('user')->whereIn('user_id', $people->modelKeys())->latest()->limit(30)->get(),
            'date' => $day->toDateString(),
            'timezone' => $timezone,
            'people' => $people,
        ]);
    }

    public function clock(Request $request, AttendanceClock $clock): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->recordsOwnAttendance(), 403);
        $data = $request->validate([
            'punch_type' => ['required', Rule::in(['in', 'out', 'break_start', 'break_end'])],
        ]);

        $clock->punch($request, $actor, $data['punch_type']);

        return back()->with('status', 'Attendance recorded. Signing in does not mark you present; this punch does.');
    }

    public function correct(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->recordsOwnAttendance(), 403);
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_in' => ['nullable', 'date_format:H:i', 'required_without:requested_out'],
            'requested_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (($data['requested_in'] ?? null) && ($data['requested_out'] ?? null) && $data['requested_out'] <= $data['requested_in']) {
            return back()->withErrors(['requested_out' => 'Clock-out must be after clock-in.'])->withInput();
        }

        $correction = AttendanceCorrection::query()->create([
            'user_id' => $actor->id,
            'work_date' => $data['work_date'],
            'requested_in' => $data['requested_in'] ?? null,
            'requested_out' => $data['requested_out'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $manager = $actor->profile?->manager;
        $manager?->notify(new PortalStatusNotification(
            'Attendance correction waiting',
            $actor->name.' asked to correct '.$data['work_date'].'.',
            route('portal.attendance.index'),
        ));

        return back()->with('status', 'Correction request '.$correction->id.' is waiting for approval.');
    }

    public function decideCorrection(Request $request, AttendanceCorrection $correction, CorrectionDecision $decisions): RedirectResponse
    {
        $actor = $this->actor($request);
        $correction->load('user.profile');
        abort_unless($correction->status === 'pending', 403);
        abort_unless($this->canDecide($actor, $correction->user), $this->access->canViewUser($actor, $correction->user) ? 403 : 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $decisions->apply($actor, $correction, $data['decision'], $data['decision_note'] ?? null, $request->ip());

        return back()->with('status', 'Attendance correction '.$data['decision'].'.');
    }

    public function overtime(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->recordsOwnAttendance(), 403);
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        OvertimeRequest::query()->create([
            'user_id' => $actor->id,
            'work_date' => $data['work_date'],
            'minutes' => $data['minutes'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $actor->profile?->manager?->notify(new PortalStatusNotification(
            'Overtime request waiting',
            $actor->name.' requested '.$data['minutes'].' overtime minutes.',
            route('portal.attendance.index'),
        ));

        return back()->with('status', 'Overtime request is waiting for approval.');
    }

    public function decideOvertime(Request $request, OvertimeRequest $overtime, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $overtime->load('user.profile');
        abort_unless($overtime->status === 'pending', 403);
        abort_unless($this->canDecide($actor, $overtime->user), $this->access->canViewUser($actor, $overtime->user) ? 403 : 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $overtime->forceFill([
            'status' => $data['decision'],
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ])->save();
        $audit->write($actor, 'overtime.'.$overtime->status, $overtime, [], $request->ip());
        $overtime->user?->notify(new PortalStatusNotification(
            'Overtime request '.$overtime->status,
            'Your overtime request was '.$overtime->status.'.',
            route('portal.attendance.index'),
        ));

        return back()->with('status', 'Overtime request '.$data['decision'].'.');
    }

    public function report(Request $request, AttendanceReview $review): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->portal_role?->canAdminister() === true, 403);
        [$from, $to] = $this->window($request);
        $people = $this->rosterPeople($actor);
        $person = $this->chosen($request, $people);
        abort_unless($person instanceof User, 404);

        return view('portal.attendance.report', [
            'person' => $person,
            'people' => $people,
            'report' => $review->period($person, $from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'range' => $request->string('range')->toString(),
            'punches' => $this->punchesOn($person, $request->string('day')->toString(), true),
            'day' => $request->string('day')->toString(),
        ]);
    }

    public function compare(Request $request, AttendanceReview $review): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->portal_role?->canAdminister() === true, 403);
        [$from, $to] = $this->window($request);
        $people = $this->rosterPeople($actor);
        $chosen = $this->chosenMany($request, $people);
        $rows = $review->compare($chosen, $from, $to);
        $sort = $request->string('sort')->toString();
        if (in_array($sort, ['name', 'rate', 'hours', 'late'], true)) {
            $rows = collect($rows)->sortBy(match ($sort) {
                'name' => fn (array $row) => $row['user']->name,
                'rate' => fn (array $row) => $row['rate'] ?? -1,
                'hours' => fn (array $row) => $row['tracked_minutes'],
                'late' => fn (array $row) => $row['late'],
            }, SORT_REGULAR, $request->string('dir')->toString() === 'desc')->values()->all();
        }

        return view('portal.attendance.compare', [
            'people' => $people,
            'chosen' => $chosen,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'range' => $request->string('range')->toString(),
            'sort' => $sort,
        ]);
    }

    public function exceptions(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->portal_role?->canAdminister() === true, 403);
        $people = $this->rosterPeople($actor);
        $status = $request->string('status')->toString();
        if (! in_array($status, ['pending', 'approved', 'rejected', ''], true)) {
            $status = 'pending';
        }
        if ($status === '') {
            $status = 'pending';
        }
        $query = $request->string('q')->toString();
        $date = $request->string('date')->toString();
        $ids = $people->modelKeys() ?: [-1];
        $corrections = AttendanceCorrection::query()->with('user.profile')->whereIn('user_id', $ids)->where('status', $status)->latest()->limit(40)->get();
        $overtime = OvertimeRequest::query()->with('user')->whereIn('user_id', $ids)->where('status', $status)->latest()->limit(40)->get();
        if ($query !== '') {
            $corrections = $corrections->filter(fn (AttendanceCorrection $item): bool => str_contains(mb_strtolower($item->user?->name ?? ''), mb_strtolower($query)))->values();
            $overtime = $overtime->filter(fn (OvertimeRequest $item): bool => str_contains(mb_strtolower($item->user?->name ?? ''), mb_strtolower($query)))->values();
        }
        if ($date !== '') {
            $corrections = $corrections->filter(fn (AttendanceCorrection $item): bool => $item->work_date->toDateString() === $date)->values();
            $overtime = $overtime->filter(fn (OvertimeRequest $item): bool => $item->work_date->toDateString() === $date)->values();
        }

        return view('portal.attendance.exceptions', [
            'people' => $people,
            'corrections' => $corrections,
            'overtime' => $overtime,
            'status' => $status,
            'query' => $query,
            'date' => $date,
            'originals' => $corrections->mapWithKeys(fn (AttendanceCorrection $item): array => [
                $item->id => $item->user instanceof User ? $this->punchesOn($item->user, $item->work_date->toDateString()) : collect(),
            ]),
        ]);
    }

    public function export(Request $request, AttendanceReview $review): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->portal_role?->canAdminister() === true, 403);
        [$from, $to] = $this->window($request);
        $people = $this->rosterPeople($actor);
        $kind = $request->string('kind')->toString();
        if ($kind === 'compare') {
            $chosen = $this->chosenMany($request, $people);
            abort_if($chosen->count() < 2, 422);
            $rows = $review->compare($chosen, $from, $to);
            $filename = 'royal-indian-stones-attendance-comparison-'.$from->toDateString().'.csv';

            return $this->csv($filename, ['Employee', 'Scheduled days', 'Days worked', 'Attendance rate', 'Hours worked', 'Average day', 'Late days', 'Leave', 'Missed punches', 'Unsettled days'], array_map(fn (array $row): array => [
                $row['user']->name,
                (string) $row['scheduled'],
                (string) $row['worked'],
                $row['rate'] === null ? '' : $row['rate'].'%',
                $row['tracked_label'],
                $row['average_label'],
                (string) $row['late'],
                (string) $row['leave'],
                (string) $row['missed'],
                (string) $row['unsettled'],
            ], $rows));
        }

        $person = $this->chosen($request, $people);
        abort_unless($person instanceof User, 404);
        $report = $review->period($person, $from, $to);
        $filename = 'royal-indian-stones-attendance-'.$person->id.'-'.$from->toDateString().'.csv';
        $lines = [[$person->name, $report['from'], $report['to'], $report['tracked_label'], $report['rate'] === null ? '' : $report['rate'].'%']];
        foreach ($report['days'] as $day) {
            $lines[] = [$day['date'], $day['status'], $day['in'], $day['out'], $day['hours']];
        }

        return $this->csv($filename, ['Date or employee', 'Status or end date', 'First in or tracked', 'Last out or rate', 'Hours'], $lines);
    }

    /**
     * @param  Collection<int, User>  $people
     */
    private function overview(Request $request, AttendanceReview $review, User $actor, Collection $people): View
    {
        $day = $request->filled('date') ? Carbon::parse($request->string('date')->toString())->startOfDay() : now()->startOfDay();
        $filtered = $this->narrow($request, $people);
        $dayView = $review->day($filtered, $day);
        $status = $request->string('status')->toString();
        $statuses = ['present', 'late', 'not_in_yet', 'missed_punch', 'leave', 'absent'];
        if (! in_array($status, $statuses, true)) {
            $status = '';
        }
        $roster = $status === '' ? $dayView['roster'] : $dayView['roster']->where('state', $status)->values();
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $roster->forPage($page, 20)->values(),
            $roster->count(),
            20,
            $page,
            ['path' => url()->current(), 'query' => $request->query()],
        );
        $selected = $filtered->firstWhere('id', $request->integer('user_id'));
        if (! $selected instanceof User) {
            $selected = $filtered->firstWhere('id', $people->firstWhere('id', $request->integer('user_id'))?->id);
        }
        abort_if($request->filled('user_id') && ! $selected instanceof User, 404);

        return view('portal.attendance.overview', [
            'date' => $day->toDateString(),
            'status' => $status,
            'query' => $request->string('q')->toString(),
            'departmentId' => $request->integer('department_id'),
            'shiftId' => $request->integer('shift_id'),
            'locationId' => $request->integer('location_id'),
            'counts' => $dayView['counts'],
            'roster' => $paginator,
            'selected' => $selected,
            'punches' => $selected instanceof User ? $this->punchesOn($selected, $day->toDateString()) : collect(),
            'events' => $review->dayEvents($filtered, $day),
            'departments' => Department::query()->orderBy('name')->get(),
            'shifts' => Shift::query()->orderBy('name')->get(),
            'locations' => WorkLocation::query()->orderBy('name')->get(),
            'people' => $people,
            'timezone' => PortalTime::timezone($actor),
            'clocks' => $actor->recordsOwnAttendance(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function rosterPeople(User $actor): Collection
    {
        $people = $this->access->peopleQuery($actor)
            ->with('profile.department', 'profile.location', 'profile.shift')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->recordsOwnAttendance())
            ->values();
        if ($actor->recordsOwnAttendance() && ! $people->contains('id', $actor->id)) {
            $actor->load('profile.department', 'profile.location', 'profile.shift');
            $people->prepend($actor);
        }

        return $people;
    }

    /**
     * @param  Collection<int, User>  $people
     * @return Collection<int, User>
     */
    private function narrow(Request $request, Collection $people): Collection
    {
        $query = mb_strtolower($request->string('q')->toString());
        $departmentId = $request->integer('department_id');
        $shiftId = $request->integer('shift_id');
        $locationId = $request->integer('location_id');

        return $people->filter(function (User $person) use ($query, $departmentId, $shiftId, $locationId): bool {
            if ($query !== '' && ! str_contains(mb_strtolower($person->name), $query)) {
                return false;
            }
            if ($departmentId > 0 && $person->profile?->department_id !== $departmentId) {
                return false;
            }
            if ($shiftId > 0 && $person->profile?->shift_id !== $shiftId) {
                return false;
            }
            if ($locationId > 0 && $person->profile?->work_location_id !== $locationId) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request): array
    {
        $today = now()->timezone('Asia/Kolkata')->startOfDay();
        $range = $request->string('range')->toString();
        if ($request->filled('from') && $request->filled('to') && $range === '') {
            $from = Carbon::parse($request->string('from')->toString())->startOfDay();
            $to = Carbon::parse($request->string('to')->toString())->startOfDay();
        } elseif ($range === '7') {
            $to = $today->copy();
            $from = $today->copy()->subDays(6);
        } elseif ($range === '30') {
            $to = $today->copy();
            $from = $today->copy()->subDays(29);
        } elseif ($range === 'month') {
            $anchor = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : $today->copy();
            $from = $anchor->copy()->startOfMonth();
            $to = $anchor->copy()->endOfMonth();
            if ($to->gt($today)) {
                $to = $today->copy();
            }
        } else {
            $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString())->startOfDay() : $today->copy()->subDays(6);
            $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString())->startOfDay() : $today->copy();
        }

        if ($to->lt($from) || (($to->getTimestamp() - $from->getTimestamp()) / 86400) > 93) {
            throw ValidationException::withMessages([
                'to' => 'Choose an end date within 93 days of the start date.',
            ]);
        }

        return [$from, $to];
    }

    /**
     * @param  Collection<int, User>  $people
     */
    private function chosen(Request $request, Collection $people): ?User
    {
        $id = $request->integer('user_id');
        if ($id === 0) {
            return $people->first();
        }

        return $people->firstWhere('id', $id);
    }

    /**
     * @param  Collection<int, User>  $people
     * @return Collection<int, User>
     */
    private function chosenMany(Request $request, Collection $people): Collection
    {
        $ids = array_values(array_unique(array_map(intval(...), (array) $request->input('employees', []))));
        if ($ids === []) {
            return $people->take(min(2, $people->count()))->values();
        }
        if (count($ids) > 4) {
            throw ValidationException::withMessages([
                'employees' => 'Choose no more than four people.',
            ]);
        }

        $chosen = $people->whereIn('id', $ids)->values();
        abort_if($chosen->count() !== count($ids), 404);

        return $chosen;
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    private function punchesOn(User $person, string $date, bool $includeReplaced = false): Collection
    {
        if ($date === '') {
            return collect();
        }
        $timezone = PortalTime::timezone($person);

        return AttendancePunch::query()
            ->where('user_id', $person->id)
            ->when(! $includeReplaced, fn ($query) => $query->whereNull('superseded_at'))
            ->where('punched_at', '>=', Carbon::parse($date, $timezone)->subDay())
            ->where('punched_at', '<', Carbon::parse($date, $timezone)->addDays(2))
            ->orderBy('punched_at')
            ->get()
            ->filter(fn (AttendancePunch $punch): bool => $punch->punched_at->copy()->timezone($punch->timezone ?: $timezone)->toDateString() === $date)
            ->values();
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    private function csv(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, $headings);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function canDecide(User $actor, ?User $subject): bool
    {
        if ($subject === null || $actor->is($subject)) {
            return false;
        }

        if ($this->access->canAdminister($actor)) {
            return true;
        }

        return ($actor->portal_role?->isManagerLevel() ?? false)
            && $subject->profile?->manager_id === $actor->id;
    }
}
