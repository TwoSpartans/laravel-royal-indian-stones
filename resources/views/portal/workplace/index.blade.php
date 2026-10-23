@extends('portal.layout')
@section('title', 'Workplace')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Office, factory, warehouse</p>
    <h1>Networks and reviews</h1>
    <p class="muted">A matching address or approved device is a signal, not proof that someone is on site.</p>
  </div>
</header>
<section class="card">
  <h2>Approved networks</h2>
  @forelse ($networks as $network)
    <p>{{ $network->label }} · {{ $network->location?->name }} · {{ $network->cidr }}
      <form method="post" action="{{ route('portal.workplace.networks.destroy', $network) }}">@csrf @method('DELETE')<button class="btn-secondary" type="submit">Remove</button></form>
    </p>
  @empty
    <div class="empty"><strong>No approved networks</strong><p>Until a range is saved, sign-ins are marked unable to verify.</p></div>
  @endforelse
  <form method="post" action="{{ route('portal.workplace.networks.store') }}" class="form-grid">
    @csrf
    <div class="field"><label for="work_location_id">Place</label><select id="work_location_id" name="work_location_id">@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
    <div class="field"><label for="label">Label</label><input id="label" name="label" required></div>
    <div class="field"><label for="cidr">IP or CIDR</label><input id="cidr" name="cidr" placeholder="203.0.113.0/24" required></div>
    <button type="submit">Save network</button>
  </form>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Remote or field work</h2>
  <p class="help">This path keeps a genuine off-site day from being labelled as the office.</p>
  @foreach ($grants as $grant)
    <p>{{ $grant->user?->name }} · {{ $grant->starts_on->format('d M') }} – {{ $grant->ends_on->format('d M Y') }} · {{ $grant->status }} · {{ $grant->reason }}</p>
  @endforeach
  <form method="post" action="{{ route('portal.workplace.remote.store') }}" class="form-grid">
    @csrf
    <div class="field"><label for="remote_user">Person</label><select id="remote_user" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
    <div class="field"><label for="starts_on">From</label><input id="starts_on" name="starts_on" type="date" required></div>
    <div class="field"><label for="ends_on">To</label><input id="ends_on" name="ends_on" type="date" required></div>
    <div class="field span-2"><label for="reason">Reason</label><input id="reason" name="reason" required></div>
    <button type="submit">Approve remote or field work</button>
  </form>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Devices</h2>
  @forelse ($devices as $device)
    <p>{{ $device->user?->name }} · {{ $device->label }} · {{ $device->status }}
      <form method="post" action="{{ route('portal.workplace.devices.update', $device) }}">@csrf<input type="hidden" name="decision" value="approved"><button type="submit">Approve</button></form>
      <form method="post" action="{{ route('portal.workplace.devices.update', $device) }}">@csrf<input type="hidden" name="decision" value="revoked"><button class="btn-secondary" type="submit">Revoke</button></form>
    </p>
  @empty
    <div class="empty"><strong>No browsers registered yet</strong><p>A device record is created when someone signs in.</p></div>
  @endforelse
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Holidays and leave types</h2>
  <form method="post" action="{{ route('portal.workplace.holidays.store') }}" class="form-grid">
    @csrf
    <div class="field"><label for="holiday_name">Holiday</label><input id="holiday_name" name="name" required></div>
    <div class="field"><label for="holiday_on">Date</label><input id="holiday_on" name="holiday_on" type="date" required></div>
    <div class="field"><label for="holiday_location">Location</label><select id="holiday_location" name="work_location_id"><option value="">All sites</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
    <button type="submit">Add holiday</button>
  </form>
  @foreach ($holidays as $holiday)
    <p>{{ $holiday->holiday_on->format('d M Y') }} · {{ $holiday->name }} · {{ $holiday->location?->name ?? 'All sites' }}</p>
  @endforeach
  @foreach ($leaveTypes as $type)
    <form method="post" action="{{ route('portal.workplace.leave-types.update', $type) }}" class="form-grid">
      @csrf @method('PUT')
      <div class="field"><label>{{ $type->name }} allowance</label><input name="annual_allowance" value="{{ $type->annual_allowance }}"></div>
      <div class="field"><label>HR threshold days</label><input name="hr_threshold_days" value="{{ $type->hr_threshold_days }}"></div>
      <input type="hidden" name="is_active" value="0"><div class="field check"><input id="active{{ $type->id }}" name="is_active" type="checkbox" value="1" @checked($type->is_active)><label for="active{{ $type->id }}">Active</label></div>
      <input type="hidden" name="needs_hr_approval" value="0"><div class="field check"><input id="hr{{ $type->id }}" name="needs_hr_approval" type="checkbox" value="1" @checked($type->needs_hr_approval)><label for="hr{{ $type->id }}">HR approval</label></div>
      <button type="submit">Update {{ $type->name }}</button>
    </form>
  @endforeach
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Access review</h2>
  @foreach ($events as $event)
    <article>
      <p><span class="badge @if($event->classification === 'approved_office') badge-good @elseif($event->classification === 'remote_authorized') badge-info @elseif($event->classification === 'unable_to_verify') badge-warn @else badge-bad @endif">{{ $event->classification_label }}</span>
        {{ $event->user?->name ?? $event->attempted_email }} · {{ $event->event_type }} · {{ \App\Support\PortalTime::format($event->occurred_at, $event->user, $event->timezone) }}</p>
      <p class="help">{{ $event->classification_reason }} IP {{ $event->ip_address ?: 'none' }}. Device {{ $event->device_status ?: 'none' }}. Forwarded header {{ $event->forwarded_header_accepted ? 'accepted' : 'not used' }}.</p>
      @if (! $event->reviewed_at && in_array($event->classification, ['outside_network', 'unable_to_verify'], true))
        <form method="post" action="{{ route('portal.workplace.events.review', $event) }}">
          @csrf
          <div class="field"><label for="decision{{ $event->id }}">Review</label>
            <select id="decision{{ $event->id }}" name="review_decision"><option value="confirmed_expected">Expected</option><option value="needs_follow_up">Needs follow-up</option></select>
          </div>
          <div class="field"><label for="note{{ $event->id }}">Note</label><input id="note{{ $event->id }}" name="review_note" required></div>
          <button type="submit">Save review</button>
        </form>
      @elseif ($event->reviewed_at)
        <p>Reviewed: {{ str_replace('_', ' ', $event->review_decision) }}. {{ $event->review_note }}</p>
      @endif
    </article>
  @endforeach
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Shifts</h2>
  @foreach ($shifts as $shift)
    <p>{{ $shift->name }} · {{ substr((string) $shift->starts_at, 0, 5) }}–{{ substr((string) $shift->ends_at, 0, 5) }} · grace {{ $shift->grace_minutes }} min · weekly off {{ implode(', ', $shift->weeklyOffDays()) }}</p>
  @endforeach
</section>
@endsection
