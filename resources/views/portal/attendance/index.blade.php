@extends('portal.layout')
@section('title', 'Attendance')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>Shifts and punches</h1>
    <p class="muted">Clock actions are separate from sign-in. Times for the selected person use {{ $timezone }}.</p>
  </div>
</header>
@if (auth()->user()->recordsOwnAttendance())
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
@if ($people->isEmpty())
  <div class="empty"><strong>No one is on the attendance roster yet</strong><p>Employees and Manager / HR appear here after they join. The employer account stays off this list.</p></div>
@else
<nav class="grid stats" aria-label="Day summary">
  @foreach (['present' => 'Present', 'late' => 'Late', 'not_in_yet' => 'Not in yet', 'missed_punch' => 'Missed punch', 'leave' => 'On leave', 'absent' => 'Absent'] as $key => $label)
    <a class="card stat {{ $status === $key ? 'is-selected' : '' }}" href="{{ route('portal.attendance.index', array_filter(['date' => $date, 'status' => $status === $key ? null : $key, 'user_id' => $selected?->id])) }}">
      <strong>{{ $counts[$key] ?? 0 }}</strong>
      <span>{{ $label }}</span>
    </a>
  @endforeach
</nav>
<form class="filters" method="get">
  <input type="hidden" name="status" value="{{ $status }}">
  <div class="field"><label for="date">Date</label><input id="date" name="date" type="date" value="{{ $date }}"></div>
  <div class="field"><label for="user_id">Person</label>
    <select id="user_id" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}" @selected($selected->is($person))>{{ $person->name }}</option>@endforeach</select>
  </div>
  <button type="submit">Show</button>
</form>
<div class="table-wrap">
  @if ($roster->isEmpty())
    <div class="empty"><strong>No one in this status</strong><p>Choose another count or clear the filter.</p></div>
  @else
  <table>
    <thead><tr><th>Person</th><th>Status</th><th>First in</th><th>Last out</th><th>Hours</th></tr></thead>
    <tbody>
      @foreach ($roster as $row)
        <tr @class(['is-selected' => $selected?->is($row['user'])])>
          <td data-label="Person"><a href="{{ route('portal.attendance.index', array_filter(['date' => $date, 'user_id' => $row['user']->id, 'status' => $status ?: null])) }}">{{ $row['user']->name }}</a></td>
          <td data-label="Status"><span class="badge">{{ $row['label'] }}</span>@if ($row['state'] === 'late' && $row['late_minutes'] > 0) <span class="muted">{{ $row['late_minutes'] }} min late</span>@endif</td>
          <td data-label="First in">{{ $row['first_in'] ? \App\Support\PortalTime::format($row['first_in']->punched_at, $row['user'], $row['first_in']->timezone) : '—' }}</td>
          <td data-label="Last out">{{ $row['last_out'] ? \App\Support\PortalTime::format($row['last_out']->punched_at, $row['user'], $row['last_out']->timezone) : '—' }}</td>
          <td data-label="Hours">@if ($row['hours'] === null)—@elseif ($row['closed']){{ $row['hours'] }}@else Unsettled @endif</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
<section class="card" style="margin-top:0.9rem">
  <h2>Punches on {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }} for {{ $selected?->name }}</h2>
  @forelse ($punches as $punch)
    <p>{{ \App\Support\PortalTime::format($punch->punched_at, $selected, $punch->timezone) }} · {{ str_replace('_', ' ', $punch->punch_type) }} @if($punch->is_correction)<span class="badge">Correction</span>@endif</p>
  @empty
    <div class="empty"><strong>No punches for this date</strong>@if (auth()->user()->recordsOwnAttendance())<p>Use clock in when the shift starts.</p>@endif</div>
  @endforelse
</section>
@if (auth()->user()->recordsOwnAttendance())
<div class="grid cards-2" style="margin-top:0.9rem">
  <section class="card">
    <h2>Request a correction</h2>
    <form method="post" action="{{ route('portal.attendance.corrections.store') }}">
      @csrf
      <div class="field"><label for="work_date">Date</label><input id="work_date" name="work_date" type="date" required></div>
      <div class="field"><label for="requested_in">Clock in</label><input id="requested_in" name="requested_in" type="time"></div>
      <div class="field"><label for="requested_out">Clock out</label><input id="requested_out" name="requested_out" type="time"></div>
      <div class="field"><label for="reason">Reason</label><textarea id="reason" name="reason" required></textarea></div>
      <button type="submit">Send for approval</button>
    </form>
  </section>
  <section class="card">
    <h2>Request overtime</h2>
    <form method="post" action="{{ route('portal.attendance.overtime.store') }}">
      @csrf
      <div class="field"><label for="ot_date">Date</label><input id="ot_date" name="work_date" type="date" required></div>
      <div class="field"><label for="minutes">Minutes</label><input id="minutes" name="minutes" inputmode="numeric" required></div>
      <div class="field"><label for="ot_reason">Reason</label><textarea id="ot_reason" name="reason" required></textarea></div>
      <button type="submit">Send for approval</button>
    </form>
  </section>
</div>
@endif
@endif
@php
  $pendingCorrections = $corrections->where('status', 'pending');
  $decidedCorrections = $corrections->where('status', '!=', 'pending');
  $pendingOvertime = $overtime->where('status', 'pending');
  $decidedOvertime = $overtime->where('status', '!=', 'pending');
@endphp
<section class="card" id="queue">
  <h2>Waiting for a decision</h2>
  @forelse ($pendingCorrections as $correction)
    <div class="queue-item">
      <span>{{ $correction->user?->name }} · {{ $correction->work_date->format('d M Y') }} · in {{ $correction->requested_in ? substr((string) $correction->requested_in, 0, 5) : '—' }} · out {{ $correction->requested_out ? substr((string) $correction->requested_out, 0, 5) : '—' }}<br>{{ $correction->reason }}</span>
      @if (! auth()->user()->is($correction->user))
        <div class="actions">
          <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf
            <input type="hidden" name="decision" value="approved"><button type="submit">Approve</button>
          </form>
          <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf
            <input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button>
          </form>
        </div>
      @endif
    </div>
  @empty
  @endforelse
  @foreach ($pendingOvertime as $requestRow)
    <div class="queue-item">
      <span>{{ $requestRow->user?->name }} · {{ $requestRow->work_date->format('d M Y') }} · {{ $requestRow->minutes }} minutes<br>{{ $requestRow->reason }}</span>
      @if (! auth()->user()->is($requestRow->user))
        <div class="actions">
          <form method="post" action="{{ route('portal.attendance.overtime.decide', $requestRow) }}">@csrf
            <input type="hidden" name="decision" value="approved"><button type="submit">Approve overtime</button>
          </form>
          <form method="post" action="{{ route('portal.attendance.overtime.decide', $requestRow) }}">@csrf
            <input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button>
          </form>
        </div>
      @endif
    </div>
  @endforeach
  @if ($pendingCorrections->isEmpty() && $pendingOvertime->isEmpty())
    <div class="empty"><strong>No correction or overtime requests</strong></div>
  @endif
  @if ($decidedCorrections->isNotEmpty() || $decidedOvertime->isNotEmpty())
    <h3>Already decided</h3>
    @foreach ($decidedCorrections as $correction)
      <p class="muted">{{ $correction->user?->name }} · {{ $correction->work_date->format('d M Y') }} · {{ str_replace('_', ' ', $correction->status) }}</p>
    @endforeach
    @foreach ($decidedOvertime as $requestRow)
      <p class="muted">{{ $requestRow->user?->name }} · {{ $requestRow->minutes }} minutes · {{ str_replace('_', ' ', $requestRow->status) }}</p>
    @endforeach
  @endif
</section>
@endsection
