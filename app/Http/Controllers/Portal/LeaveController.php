<?php

namespace App\Http\Controllers\Portal;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\PortalStatusNotification;
use App\Services\Portal\LeaveDays;
use App\Services\Portal\LeaveDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $people = $this->access->peopleQuery($actor)->orderBy('name')->get();
        $status = $request->string('status')->toString();
        $requests = LeaveRequest::query()
            ->with('user', 'type')
            ->whereIn('user_id', $people->modelKeys() ?: [-1])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $month = $request->filled('month') ? $request->string('month')->toString() : now()->format('Y-m');
        $start = Carbon::parse($month.'-01');
        $calendar = LeaveRequest::query()
            ->with('user', 'type')
            ->whereIn('user_id', $people->modelKeys() ?: [-1])
            ->whereIn('status', ['approved', 'pending_manager', 'pending_hr'])
            ->whereDate('starts_on', '<=', $start->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->orderBy('starts_on')
            ->get();

        return view('portal.leave.index', [
            'requests' => $requests,
            'types' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'balances' => LeaveBalance::query()->with('type')->where('user_id', $actor->id)->where('year', (int) now()->year)->get(),
            'calendar' => $calendar,
            'month' => $month,
            'status' => $status,
        ]);
    }

    public function store(Request $request, LeaveDays $days): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->recordsOwnAttendance(), 403);
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $type = LeaveType::query()->findOrFail($data['leave_type_id']);
        $count = $days->count($actor, $data['starts_on'], $data['ends_on']);
        if ($count < 1) {
            return back()->withErrors(['ends_on' => 'That range has no working days after weekly offs and holidays.'])->withInput();
        }

        if ($type->is_paid) {
            $balance = LeaveBalance::query()
                ->where('user_id', $actor->id)
                ->where('leave_type_id', $type->id)
                ->where('year', (int) Carbon::parse($data['starts_on'])->year)
                ->first();
            if ($balance === null || $balance->remaining() < $count) {
                return back()->withErrors(['leave_type_id' => 'The available balance is not enough for this request.'])->withInput();
            }
        }

        $status = $actor->profile?->manager_id ? 'pending_manager' : 'pending_hr';

        $leave = LeaveRequest::query()->create([
            'user_id' => $actor->id,
            'leave_type_id' => $type->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'days' => $count,
            'reason' => $data['reason'],
            'status' => $status,
        ]);

        $recipient = $status === 'pending_manager' ? $actor->profile?->manager : null;
        $recipient?->notify(new PortalStatusNotification(
            'Leave request waiting',
            $actor->name.' requested '.$count.' day(s) of '.$type->name.'.',
            route('portal.leave.index'),
        ));

        return back()->with('status', 'Leave request submitted. Status: '.str_replace('_', ' ', $leave->status).'.');
    }

    public function decide(Request $request, LeaveRequest $leave, LeaveDecision $decisions): RedirectResponse
    {
        $actor = $this->actor($request);
        $leave->load('user.profile', 'type');
        $visible = $leave->user !== null && ($this->access->canViewUser($actor, $leave->user) || $actor->is($leave->user));
        abort_unless($visible, 404);
        abort_unless($this->access->canDecideLeave($actor, $leave), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $decisions->apply($actor, $leave, $data['decision'], $data['decision_note'] ?? null, $request->ip());

        return back()->with('status', 'Leave decision saved.');
    }
}
