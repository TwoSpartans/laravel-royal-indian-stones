@extends('portal.layout')
@section('title', 'Attendance')
@section('content')
@php
  $filters = array_filter([
    'date' => $date,
    'q' => $query,
    'department_id' => $departmentId ?: null,
    'shift_id' => $shiftId ?: null,
    'location_id' => $locationId ?: null,
  ]);
  $total = max(1, (int) $counts->sum());
  $checked = (int) (($counts['present'] ?? 0) + ($counts['late'] ?? 0));
  $waiting = (int) ($counts['not_in_yet'] ?? 0);
@endphp
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>Attendance overview</h1>
    <p class="muted">Times use {{ $timezone }}. Signing in does not mark anyone present.</p>
  </div>
</header>
@include('portal.attendance.partials.tabs')
@if ($clocks)
<form method="post" action="{{ route('portal.attendance.clock') }}" class="btn-row">
  @csrf
  <button class="clock-btn" name="punch_type" value="in">Clock in</button>
  <button class="clock-btn btn-secondary" name="punch_type" value="break_start">Start break</button>
  <button class="clock-btn btn-secondary" name="punch_type" value="break_end">End break</button>
  <button class="clock-btn" name="punch_type" value="out">Clock out</button>
</form>
@else
<p class="muted">This account runs the roster. It does not clock in, request corrections, or apply for leave.</p>
@endif
<nav class="grid stats" aria-label="Day summary">
  @foreach (['present' => ['Checked in', 'good'], 'not_in_yet' => ['Not in yet', 'warn'], 'late' => ['Late', 'warn'], 'leave' => ['On leave', 'info'], 'absent' => ['Absent', 'bad'], 'missed_punch' => ['Missing punch', 'bad']] as $key => [$label, $tone])
    <a data-tone="{{ $tone }}" class="card stat {{ $status === $key ? 'is-selected' : '' }}" href="{{ route('portal.attendance.index', array_merge($filters, ['status' => $status === $key ? null : $key])) }}">
      <strong>{{ $counts[$key] ?? 0 }}</strong>
      <span>{{ $label }}</span>
    </a>
  @endforeach
</nav>
<form class="filters att-filters" method="get">
  @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
  <div class="field"><label for="date">Date</label><input id="date" name="date" type="date" value="{{ $date }}"></div>
  <div class="field"><label for="q">Search</label><input id="q" name="q" value="{{ $query }}" placeholder="Employee name"></div>
  <div class="field"><label for="department_id">Department</label>
    <select id="department_id" name="department_id"><option value="">All</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="shift_id">Shift</label>
    <select id="shift_id" name="shift_id"><option value="">All</option>@foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected($shiftId === $shift->id)>{{ $shift->name }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="location_id">Location</label>
    <select id="location_id" name="location_id"><option value="">All</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>@endforeach</select>
  </div>
  <button type="submit">Show</button>
</form>
<div class="attendance-split">
  <section class="card">
    <div class="card-head">
      <div>
        <h2>Roster</h2>
        <p class="muted">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }} · {{ $roster->total() }} {{ $roster->total() === 1 ? 'person' : 'people' }}</p>
      </div>
      <a href="{{ route('portal.attendance.exceptions') }}">Approval queue</a>
    </div>
    @if ($roster->isEmpty())
      <div class="empty"><strong>No one matches</strong><p>Change the date, search, or status.</p></div>
    @else
    <div class="table-wrap">
      <table>
        <thead><tr><th>Employee</th><th>Shift</th><th>Status</th><th>First in</th><th>Last out</th><th>Hours</th></tr></thead>
        <tbody>
          @foreach ($roster as $row)
            @php
              $parts = preg_split('/\s+/', trim($row['user']->name)) ?: [];
              $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
              $tone = match ($row['state']) {
                'present' => 'good',
                'late' => 'warn',
                'absent', 'missed_punch' => 'bad',
                'leave' => 'info',
                default => 'wait',
              };
            @endphp
            <tr @class(['is-selected' => $selected?->is($row['user'])])>
              <td data-label="Employee">
                <a class="person-cell" href="{{ route('portal.attendance.index', array_merge($filters, array_filter(['status' => $status ?: null, 'user_id' => $row['user']->id]))) }}">
                  <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                  <span><strong>{{ $row['user']->name }}</strong><small>{{ $row['user']->profile?->department?->name }}</small></span>
                </a>
              </td>
              <td data-label="Shift">{{ $row['user']->profile?->shift?->name ?? '—' }}</td>
              <td data-label="Status"><span class="badge badge-{{ $tone }}">{{ $row['label'] }}</span>@if ($row['state'] === 'late' && $row['late_minutes'] > 0) <span class="muted">{{ $row['late_minutes'] }} min late</span>@endif</td>
              <td data-label="First in">{{ $row['first_in'] ? \App\Support\PortalTime::format($row['first_in']->punched_at, $row['user'], $row['first_in']->timezone) : '—' }}</td>
              <td data-label="Last out">{{ $row['last_out'] ? \App\Support\PortalTime::format($row['last_out']->punched_at, $row['user'], $row['last_out']->timezone) : '—' }}</td>
              <td data-label="Hours">@if ($row['hours'] === null)—@elseif ($row['closed']){{ $row['hours'] }}@else Unsettled @endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $roster->links() }}
    @endif
  </section>
  <aside class="attendance-side">
    <section class="card">
      <h2>Team status</h2>
      <div class="mix-bar" aria-hidden="true">
        <span style="width: {{ round($checked / $total * 100) }}%"></span>
        <span style="width: {{ round($waiting / $total * 100) }}%"></span>
      </div>
      <ul class="legend">
        <li><i class="dot dot-good"></i> {{ $checked }} checked in</li>
        <li><i class="dot dot-warn"></i> {{ $waiting }} not in yet</li>
        <li><i class="dot"></i> {{ max(0, (int) $counts->sum() - $checked - $waiting) }} other</li>
      </ul>
    </section>
    <section class="card">
      <h2>Recent punches</h2>
      @forelse ($events as $event)
        <p>{{ \App\Support\PortalTime::format($event->punched_at, $event->user, $event->timezone) }} · {{ $event->user?->name }} · {{ str_replace('_', ' ', $event->punch_type) }}</p>
      @empty
        <div class="empty"><strong>No punches on this date</strong></div>
      @endforelse
    </section>
  </aside>
</div>
@if ($selected)
<dialog class="punch-drawer" open aria-labelledby="punch-title">
  <div class="punch-drawer-head">
    <h2 id="punch-title">{{ $selected->name }}</h2>
    <a class="btn-secondary" href="{{ route('portal.attendance.index', $filters) }}">Close</a>
  </div>
  <p class="muted">{{ $selected->profile?->shift?->name ?? 'No shift' }} · {{ $selected->profile?->location?->name ?? 'No location' }}</p>
  <p><a class="btn" href="{{ route('portal.attendance.report', ['user_id' => $selected->id, 'from' => \Illuminate\Support\Carbon::parse($date)->startOfMonth()->toDateString(), 'to' => $date]) }}">Full report</a></p>
  <h3>Punches on {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</h3>
  @forelse ($punches as $punch)
    <p>{{ \App\Support\PortalTime::format($punch->punched_at, $selected, $punch->timezone) }} · {{ str_replace('_', ' ', $punch->punch_type) }} @if($punch->is_correction)<span class="badge">Correction</span>@endif</p>
  @empty
    <div class="empty"><strong>No punches for this date</strong></div>
  @endforelse
</dialog>
@endif
@endsection
