@extends('portal.layout')
@section('title', 'Workplace')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Office, factory, warehouse</p>
    <h1>Workplace</h1>
    <p class="muted">A matching address or an approved device is a signal. It does not prove someone is physically on site.</p>
  </div>
</header>

<div class="split">
  <section class="card">
    <h2>Approved networks</h2>
    @if ($networks->isEmpty())
      <div class="empty"><strong>No approved networks</strong><p>Until a range is saved, sign-ins are marked unable to verify.</p></div>
    @else
      <table>
        <thead><tr><th>Place</th><th>Label</th><th>Range</th><th></th></tr></thead>
        <tbody>
          @foreach ($networks as $network)
            <tr>
              <td data-label="Place">{{ $network->location?->name }}</td>
              <td data-label="Label">{{ $network->label }}</td>
              <td data-label="Range">{{ $network->cidr }}</td>
              <td data-label="Action">
                <form method="post" action="{{ route('portal.workplace.networks.destroy', $network) }}">@csrf @method('DELETE')<button class="btn-secondary" type="submit">Remove</button></form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
    <form method="post" action="{{ route('portal.workplace.networks.store') }}" class="form-grid">
      @csrf
      <div class="field"><label for="work_location_id">Place</label><select id="work_location_id" name="work_location_id">@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
      <div class="field"><label for="label">Label</label><input id="label" name="label" required></div>
      <div class="field span-2"><label for="cidr">IP or CIDR</label><input id="cidr" name="cidr" placeholder="203.0.113.0/24" required></div>
      <button type="submit">Save network</button>
    </form>
  </section>

  <section class="card">
    <h2>Remote or field work</h2>
    <p class="help">This keeps a real off-site day from being labelled as the office.</p>
    @forelse ($grants as $grant)
      <p>{{ $grant->user?->name }} · {{ $grant->starts_on->format('d M') }} – {{ $grant->ends_on->format('d M Y') }} · {{ $grant->reason }}</p>
    @empty
      <div class="empty"><strong>No remote or field authorizations</strong></div>
    @endforelse
    <form method="post" action="{{ route('portal.workplace.remote.store') }}" class="form-grid">
      @csrf
      <div class="field"><label for="remote_user">Person</label><select id="remote_user" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="field"><label for="starts_on">From</label><input id="starts_on" name="starts_on" type="date" required></div>
      <div class="field"><label for="ends_on">To</label><input id="ends_on" name="ends_on" type="date" required></div>
      <div class="field"><label for="reason">Reason</label><input id="reason" name="reason" required></div>
      <button type="submit">Approve</button>
    </form>
  </section>
</div>

<section class="card">
  <h2>Devices</h2>
  @if ($devices->isEmpty())
    <div class="empty"><strong>No browsers registered yet</strong><p>A device record is created when someone signs in.</p></div>
  @else
    <table>
      <thead><tr><th>Person</th><th>Device</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach ($devices as $device)
          <tr>
            <td data-label="Person">{{ $device->user?->name }}</td>
            <td data-label="Device">{{ $device->label }}</td>
            <td data-label="Status"><span class="badge">{{ $device->status }}</span></td>
            <td data-label="Decision">
              <div class="actions">
                <form method="post" action="{{ route('portal.workplace.devices.update', $device) }}">@csrf<input type="hidden" name="decision" value="approved"><button type="submit">Approve</button></form>
                <form method="post" action="{{ route('portal.workplace.devices.update', $device) }}">@csrf<input type="hidden" name="decision" value="revoked"><button class="btn-secondary" type="submit">Revoke</button></form>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</section>

<div class="split">
  <section class="card">
    <h2>Holidays</h2>
    <form method="post" action="{{ route('portal.workplace.holidays.store') }}" class="form-grid">
      @csrf
      <div class="field"><label for="holiday_name">Holiday</label><input id="holiday_name" name="name" required></div>
      <div class="field"><label for="holiday_on">Date</label><input id="holiday_on" name="holiday_on" type="date" required></div>
      <div class="field span-2"><label for="holiday_location">Location</label><select id="holiday_location" name="work_location_id"><option value="">All sites</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
      <button type="submit">Add holiday</button>
    </form>
    @foreach ($holidays as $holiday)
      <p>{{ $holiday->holiday_on->format('d M Y') }} · {{ $holiday->name }} · {{ $holiday->location?->name ?? 'All sites' }}</p>
    @endforeach
  </section>
  <section class="card">
    <h2>Shifts</h2>
    @foreach ($shifts as $shift)
      <p>{{ $shift->name }} · {{ substr((string) $shift->starts_at, 0, 5) }}–{{ substr((string) $shift->ends_at, 0, 5) }} · {{ $shift->grace_minutes }} min grace</p>
    @endforeach
    <h2>Leave balance</h2>
    @foreach ($leaveTypes as $type)
      <form method="post" action="{{ route('portal.workplace.leave-types.update', $type) }}" class="form-grid stacked-form">
        @csrf @method('PUT')
        <div class="field"><label for="allowance{{ $type->id }}">Days each year</label><input id="allowance{{ $type->id }}" name="annual_allowance" value="{{ $type->allowanceForInput() }}"></div>
        <div class="field"><label for="threshold{{ $type->id }}">Second approval after</label><input id="threshold{{ $type->id }}" name="hr_threshold_days" value="{{ $type->hr_threshold_days }}"></div>
        <input type="hidden" name="is_active" value="0">
        <input type="hidden" name="needs_hr_approval" value="0">
        <label class="check" for="active{{ $type->id }}"><input id="active{{ $type->id }}" name="is_active" type="checkbox" value="1" @checked($type->is_active)><span>Active</span></label>
        <label class="check" for="hr{{ $type->id }}"><input id="hr{{ $type->id }}" name="needs_hr_approval" type="checkbox" value="1" @checked($type->needs_hr_approval)><span>Needs a second approval</span></label>
        <button type="submit">Save leave balance</button>
      </form>
    @endforeach
  </section>
</div>

<section class="card">
  <h2>Sign-ins to review</h2>
  @if ($reviews->isEmpty())
    <div class="empty"><strong>No sign-ins are waiting</strong><p>Outside-network and unverified sign-ins appear here.</p></div>
  @else
    @foreach ($reviews as $event)
      <article class="review-card">
        <div>
          <span class="badge @if($event->classification === 'unable_to_verify') badge-warn @else badge-bad @endif">{{ $event->classification_label }}</span>
          <p>{{ $event->user?->name ?? $event->attempted_email }} · {{ \App\Support\PortalTime::format($event->occurred_at, $event->user, $event->timezone) }} · {{ $event->ip_address ?: 'no address' }}</p>
          <p class="help">{{ $event->classification_reason }}</p>
        </div>
        <form class="review-row" method="post" action="{{ route('portal.workplace.events.review', $event) }}">
          @csrf
          <div class="field">
            <label for="decision{{ $event->id }}">Review</label>
            <select id="decision{{ $event->id }}" name="review_decision">
              <option value="confirmed_expected">Expected</option>
              <option value="needs_follow_up">Needs follow-up</option>
            </select>
          </div>
          <div class="field">
            <label for="note{{ $event->id }}">Note</label>
            <input id="note{{ $event->id }}" name="review_note" required>
          </div>
          <button type="submit">Save</button>
        </form>
      </article>
    @endforeach
  @endif
</section>
@endsection
