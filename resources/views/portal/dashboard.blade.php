@extends('portal.layout')
@section('title', 'Today')
@section('content')
@php
  $role = auth()->user()->portal_role;
  $oversight = $role?->canAdminister() === true;
@endphp
<header class="page-head">
  <div>
    <p class="eyebrow">{{ $role?->label() }}</p>
    <h1>{{ $oversight ? 'Today at the yards' : 'Your day' }}</h1>
    <p class="muted">Times use {{ \App\Support\PortalTime::timezone(auth()->user()) }}. Signing in does not mark anyone present.</p>
  </div>
</header>

@if ($self && auth()->user()->recordsOwnAttendance() && auth()->user()->canOpenPortalSection('attendance'))
  <section class="card" style="margin-bottom:1rem">
    <h2>Your attendance</h2>
    <p>Status: <span class="badge">{{ $self['label'] }}</span></p>
    @php $openPunch = $lastPunch?->punch_type; @endphp
    <form method="post" action="{{ route('portal.attendance.clock') }}" class="btn-row">
      @csrf
      <button class="clock-btn {{ $openPunch === 'in' ? 'btn-secondary' : '' }}" name="punch_type" value="in" type="submit" @disabled(in_array($openPunch, ['in', 'break_start', 'break_end'], true))>Clock in</button>
      <button class="clock-btn btn-secondary" name="punch_type" value="break_start" type="submit" @disabled($openPunch !== 'in')>Start break</button>
      <button class="clock-btn btn-secondary" name="punch_type" value="break_end" type="submit" @disabled($openPunch !== 'break_start')>End break</button>
      <button class="clock-btn {{ $openPunch === 'in' ? '' : 'btn-secondary' }}" name="punch_type" value="out" type="submit" @disabled(! in_array($openPunch, ['in', 'break_end'], true))>Clock out</button>
    </form>
    <p class="help">Last punch: {{ $lastPunch ? \App\Support\PortalTime::format($lastPunch->punched_at, auth()->user(), $lastPunch->timezone).' · '.$lastPunch->punch_type : 'none yet today' }}</p>
  </section>
@endif

@if ($workedHours)
  <section class="hours-worked" style="margin-bottom:1rem">
    <h2>Hours worked</h2>
    <div class="grid stats">
      <article class="card stat"><strong>{{ $workedHours['today'] }}</strong><span>Today</span></article>
      <article class="card stat"><strong>{{ $workedHours['month'] }}</strong><span>This month</span></article>
    </div>
    <p class="help">Counted from clock-in to clock-out. Breaks are left out. A day without clock-out is listed as unsettled.</p>
  </section>
  <section class="card" style="margin-bottom:1rem">
    <h2>Unsettled working hours</h2>
    @if ($workedHours['unsettled'] === [])
      <div class="empty"><strong>No open days</strong><p>Every recorded shift this month has a clock-out.</p></div>
    @else
      <p>Total <strong>{{ $workedHours['unsettled_total'] }}</strong></p>
      @foreach ($workedHours['unsettled'] as $day)
        <p>{{ $day['date'] }} · clocked in {{ $day['started'] }} · {{ $day['hours'] }} · Clock-out was not recorded</p>
      @endforeach
      <p class="help">These hours stay here until a clock-out, or an approved correction, closes the day. A past day is counted through the end of that day. Today is counted up to now.</p>
    @endif
  </section>
@endif

@if ($oversight)
  <section class="grid stats">
    <article class="card stat"><strong>{{ $counts['workforce'] }}</strong><span>On the roster</span></article>
    <article class="card stat" data-tone="good"><strong>{{ $counts['present'] }}</strong><span>Present, including late</span></article>
    <article class="card stat" data-tone="warn"><strong>{{ $counts['late'] }}</strong><span>Late</span></article>
    <article class="card stat" data-tone="bad"><strong>{{ $counts['absent'] }}</strong><span>Absent</span></article>
    <article class="card stat"><strong>{{ $counts['not_in_yet'] }}</strong><span>Not in yet</span></article>
    <article class="card stat"><strong>{{ $counts['leave'] }}</strong><span>On leave</span></article>
    <article class="card stat" data-tone="warn"><strong>{{ $counts['missed_punch'] }}</strong><span>Missed punch</span></article>
    <article class="card stat"><strong>{{ array_sum($approvals) }}</strong><span>Needs a decision</span></article>
  </section>
  <div class="grid cards-2" style="margin-top:0.9rem">
    <section class="card">
      <h2>Locations</h2>
      @if ($locations->isEmpty())
        <div class="empty"><strong>No roster yet</strong><p>Active staff appear here after they accept an invitation.</p></div>
      @else
        <div class="table-wrap">
          <table>
            <thead><tr><th>Place</th><th>People</th><th>Present</th><th>Absent</th></tr></thead>
            <tbody>
              @foreach ($locations as $location)
                <tr>
                  <td data-label="Place">{{ $location['name'] }}</td>
                  <td data-label="People">{{ $location['people'] }}</td>
                  <td data-label="Present">{{ $location['present'] }}</td>
                  <td data-label="Absent">{{ $location['absent'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>
    <section class="card">
      <h2>Payroll status</h2>
      @if ($payslipCounts->isEmpty())
        <div class="empty"><strong>No period prepared</strong><p>Calculations stay off until a reviewer enables them. Nothing is marked paid.</p></div>
      @else
        <ul>
          @foreach ($payslipCounts as $status => $count)
            <li>{{ $count }} {{ str_replace('_', ' ', $status) }}</li>
          @endforeach
        </ul>
      @endif
      <h2>Needs a decision</h2>
      @if (array_sum($approvals) === 0)
        <div class="empty"><strong>Nothing is waiting</strong><p>Leave, expenses, corrections, and network reviews show up here.</p></div>
      @else
        <ul class="queue">
          @if ($approvals['leave'] > 0)
            <li><a href="{{ route('portal.leave.index', ['status' => 'pending_manager']) }}">Leave <strong>{{ $approvals['leave'] }}</strong></a></li>
          @endif
          @if ($approvals['expenses'] > 0)
            <li><a href="{{ route('portal.expenses.index', ['status' => 'submitted']) }}">Expenses <strong>{{ $approvals['expenses'] }}</strong></a></li>
          @endif
          @if ($approvals['corrections'] > 0)
            <li><a href="{{ route('portal.attendance.index') }}#queue">Attendance corrections <strong>{{ $approvals['corrections'] }}</strong></a></li>
          @endif
          @if ($approvals['overtime'] > 0)
            <li><a href="{{ route('portal.attendance.index') }}#queue">Overtime <strong>{{ $approvals['overtime'] }}</strong></a></li>
          @endif
          @if ($oversight && ($approvals['devices'] + $approvals['remote'] + $approvals['reviews']) > 0)
            <li><a href="{{ route('portal.workplace.index') }}">Devices, remote work, network reviews <strong>{{ $approvals['devices'] + $approvals['remote'] + $approvals['reviews'] }}</strong></a></li>
          @endif
        </ul>
      @endif
    </section>
  </div>
@endif

<div class="grid cards-2" style="margin-top:0.9rem">
  <section class="card">
    <h2>Upcoming leave</h2>
    @forelse ($upcomingLeave as $leave)
      <p>{{ $leave->user?->name }} · {{ $leave->type?->name }} · {{ $leave->starts_on->format('d M') }}</p>
    @empty
      <div class="empty"><strong>No approved leave in the next two weeks</strong></div>
    @endforelse
  </section>
  <section class="card">
    <h2>Documents expiring</h2>
    @forelse ($expiringDocuments as $document)
      <p>{{ $document->title }} · {{ $document->user?->name }} · {{ $document->expires_on?->format('d M Y') }}</p>
    @empty
      <div class="empty"><strong>Nothing expires in the next 30 days</strong></div>
    @endforelse
  </section>
</div>

@if (auth()->user()->canOpenPortalSection('tasks'))
<section class="card" style="margin-top:0.9rem">
  <h2>{{ $oversight ? 'Open stone work' : 'Your tasks' }}</h2>
  @forelse ($tasks as $task)
    <p><a href="{{ route('portal.tasks.show', $task) }}">{{ $task->title }}</a> · {{ config('portal.task_categories.'.$task->category) }} · {{ str_replace('_', ' ', $task->status) }}</p>
  @empty
    <div class="empty"><strong>No open tasks</strong><p>Sales, sourcing, processing, inspection, packing, dispatch, and export work will show here.</p></div>
  @endforelse
</section>
@endif

@unless ($oversight)
  @if (auth()->user()->canOpenPortalSection('leave'))
  <section class="card" style="margin-top:0.9rem">
    <h2>Leave balance this year</h2>
    @if ($balance)
      <p>{{ $balance->summary() }}</p>
    @else
      <div class="empty"><strong>No balance yet</strong></div>
    @endif
  </section>
  @endif
@endunless
@endsection
