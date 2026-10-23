@extends('portal.layout')
@section('title', 'Today')
@section('content')
@php
  $role = auth()->user()->portal_role;
  $oversight = in_array($role, [\App\Enums\PortalRole::Employer, \App\Enums\PortalRole::AdminHr, \App\Enums\PortalRole::Manager], true);
@endphp
<header class="page-head">
  <div>
    <p class="eyebrow">{{ $role?->label() }}</p>
    <h1>{{ $oversight ? 'Today at the yards' : 'Your day' }}</h1>
    <p class="muted">Times use {{ \App\Support\PortalTime::timezone(auth()->user()) }}. Signing in does not mark anyone present.</p>
  </div>
</header>

@if ($self)
  <section class="card" style="margin-bottom:1rem">
    <h2>Your attendance</h2>
    <p>Status: <span class="badge">{{ $self['label'] }}</span></p>
    <form method="post" action="{{ route('portal.attendance.clock') }}" class="btn-row">
      @csrf
      <button class="clock-btn" name="punch_type" value="in" type="submit">Clock in</button>
      <button class="clock-btn btn-secondary" name="punch_type" value="break_start" type="submit">Start break</button>
      <button class="clock-btn btn-secondary" name="punch_type" value="break_end" type="submit">End break</button>
      <button class="clock-btn" name="punch_type" value="out" type="submit">Clock out</button>
    </form>
    <p class="help">Last punch: {{ $lastPunch ? \App\Support\PortalTime::format($lastPunch->punched_at, auth()->user(), $lastPunch->timezone).' · '.$lastPunch->punch_type : 'none yet today' }}</p>
  </section>
@endif

@if ($oversight)
  <section class="grid stats">
    <article class="card stat"><strong>{{ $counts['workforce'] }}</strong><span>On the roster</span></article>
    <article class="card stat"><strong>{{ $counts['present'] }}</strong><span>Present, including late</span></article>
    <article class="card stat"><strong>{{ $counts['late'] }}</strong><span>Late</span></article>
    <article class="card stat"><strong>{{ $counts['absent'] }}</strong><span>Absent</span></article>
    <article class="card stat"><strong>{{ $counts['not_in_yet'] }}</strong><span>Not in yet</span></article>
    <article class="card stat"><strong>{{ $counts['leave'] }}</strong><span>On leave</span></article>
    <article class="card stat"><strong>{{ $counts['missed_punch'] }}</strong><span>Missed punch</span></article>
    <article class="card stat"><strong>{{ array_sum($approvals) }}</strong><span>{{ $role === \App\Enums\PortalRole::Employee ? 'Waiting' : 'Needs a decision' }}</span></article>
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
      <h2>Decisions</h2>
      <ul>
        <li>{{ $approvals['leave'] }} leave</li>
        <li>{{ $approvals['expenses'] }} expenses</li>
        <li>{{ $approvals['corrections'] }} attendance corrections</li>
        <li>{{ $approvals['overtime'] }} overtime</li>
        @if ($approvals['devices'] || $approvals['remote'] || $approvals['reviews'])
          <li>{{ $approvals['devices'] }} devices, {{ $approvals['remote'] }} remote grants, {{ $approvals['reviews'] }} network reviews</li>
        @endif
      </ul>
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

<section class="card" style="margin-top:0.9rem">
  <h2>{{ $oversight ? 'Open stone work' : 'Your tasks' }}</h2>
  @forelse ($tasks as $task)
    <p><a href="{{ route('portal.tasks.show', $task) }}">{{ $task->title }}</a> · {{ config('portal.task_categories.'.$task->category) }} · {{ str_replace('_', ' ', $task->status) }}</p>
  @empty
    <div class="empty"><strong>No open tasks</strong><p>Sales, sourcing, processing, inspection, packing, dispatch, and export work will show here.</p></div>
  @endforelse
</section>

@unless ($oversight)
  <section class="card" style="margin-top:0.9rem">
    <h2>Leave balance this year</h2>
    @forelse ($balances as $balance)
      <p>{{ $balance->type?->name }}: {{ $balance->remaining() }} days left</p>
    @empty
      <div class="empty"><strong>No balance yet</strong></div>
    @endforelse
  </section>
@endunless
@endsection
