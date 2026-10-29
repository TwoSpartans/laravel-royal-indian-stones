<?php

namespace App\Http\Controllers\Portal;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\PortalStatusNotification;
use App\Services\Portal\AttendanceBoard;
use App\Services\Portal\AttendanceClock;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\CorrectionDecision;
use App\Support\PortalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends PortalController
{
    public function index(Request $request, AttendanceBoard $board): View
    {
        $actor = $this->actor($request);
        $day = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : now();
        $people = $this->access->peopleQuery($actor)
            ->with('profile.location', 'profile.shift')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->recordsOwnAttendance())
            ->values();
        if ($actor->recordsOwnAttendance() && ! $people->contains('id', $actor->id)) {
            $actor->load('profile.location', 'profile.shift');
            $people->prepend($actor);
        }

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
