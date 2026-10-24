<?php

namespace App\Http\Controllers\Portal;

use App\Models\AccessEvent;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\OfficeNetwork;
use App\Models\RegisteredDevice;
use App\Models\RemoteWorkAuthorization;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Portal\AuditWriter;
use App\Services\Portal\IpRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkplaceController extends PortalController
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);

        return view('portal.workplace.index', [
            'locations' => WorkLocation::query()->with('networks')->orderBy('name')->get(),
            'networks' => OfficeNetwork::query()->with('location')->orderBy('label')->get(),
            'holidays' => Holiday::query()->with('location')->orderBy('holiday_on')->get(),
            'shifts' => Shift::query()->orderBy('name')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
            'devices' => RegisteredDevice::query()->with('user')->latest('last_seen_at')->limit(40)->get(),
            'grants' => RemoteWorkAuthorization::query()->with('user')->latest()->limit(40)->get(),
            'reviews' => AccessEvent::query()->with('user')->whereNull('reviewed_at')->whereIn('classification', ['outside_network', 'unable_to_verify'])->latest('occurred_at')->limit(12)->get(),
            'people' => User::query()->whereNotNull('portal_role')->orderBy('name')->get(),
        ]);
    }

    public function network(Request $request, IpRange $ranges, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'label' => ['required', 'string', 'max:120'],
            'cidr' => ['required', 'string', 'max:64'],
        ]);

        if (! $ranges->isValidCidr($data['cidr'])) {
            return back()->withErrors(['cidr' => 'Enter an IP address or a CIDR range, such as 203.0.113.0/24.'])->withInput();
        }

        $network = OfficeNetwork::query()->create([
            'work_location_id' => $data['work_location_id'],
            'label' => $data['label'],
            'cidr' => trim($data['cidr']),
            'created_by' => $actor->id,
        ]);
        $audit->write($actor, 'network.saved', $network, ['cidr' => $network->cidr], $request->ip());

        return back()->with('status', 'Approved network saved. A match is a signal, not proof someone is on site.');
    }

    public function destroyNetwork(Request $request, OfficeNetwork $network, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $audit->write($actor, 'network.removed', $network, ['cidr' => $network->cidr], $request->ip());
        $network->delete();

        return back()->with('status', 'Approved network removed.');
    }

    public function holiday(Request $request): RedirectResponse
    {
        abort_unless($this->access->canAdminister($this->actor($request)), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'holiday_on' => ['required', 'date'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
        ]);

        Holiday::query()->create([
            'name' => $data['name'],
            'holiday_on' => $data['holiday_on'],
            'work_location_id' => $data['work_location_id'] ?? null,
        ]);

        return back()->with('status', 'Holiday saved.');
    }

    public function leaveType(Request $request, LeaveType $leaveType): RedirectResponse
    {
        abort_unless($this->access->canAdminister($this->actor($request)), 403);
        $data = $request->validate([
            'annual_allowance' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
            'needs_hr_approval' => ['required', 'boolean'],
            'hr_threshold_days' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);
        $leaveType->forceFill($data)->save();

        return back()->with('status', $leaveType->name.' updated. Existing balances are not rewritten.');
    }

    public function device(Request $request, RegisteredDevice $device, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'revoked'])],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $device->forceFill([
            'status' => $data['decision'],
            'label' => $data['label'] ?: $device->label,
            'approved_by' => $data['decision'] === 'approved' ? $actor->id : $device->approved_by,
            'approved_at' => $data['decision'] === 'approved' ? now() : $device->approved_at,
            'revoked_at' => $data['decision'] === 'revoked' ? now() : null,
        ])->save();
        $audit->write($actor, 'device.'.$device->status, $device, ['user_id' => $device->user_id], $request->ip());

        return back()->with('status', 'Device '.$device->status.'. An approved device is a signal, not proof of location.');
    }

    public function remote(Request $request, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $person = $this->access->peopleQuery($actor)->whereKey($data['user_id'])->first();
        abort_unless($person instanceof User, 404);

        $grant = RemoteWorkAuthorization::query()->create([
            'user_id' => $person->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'reason' => $data['reason'],
            'status' => 'approved',
            'approved_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $audit->write($actor, 'remote_work.approved', $grant, [], $request->ip());

        return back()->with('status', 'Remote or field work approved. It will not be labelled as an office network.');
    }

    public function review(Request $request, AccessEvent $event, AuditWriter $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canAdminister($actor), 403);
        $data = $request->validate([
            'review_decision' => ['required', Rule::in(['confirmed_expected', 'needs_follow_up'])],
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $event->forceFill([
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_decision' => $data['review_decision'],
            'review_note' => $data['review_note'],
        ])->save();
        $audit->write($actor, 'access_event.reviewed', $event, [
            'decision' => $data['review_decision'],
        ], $request->ip());

        return back()->with('status', 'Review saved. The original network result is unchanged.');
    }
}
