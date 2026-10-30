@extends('portal.layout')
@section('title', $person->name.' attendance')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>{{ $person->name }}</h1>
    <p class="muted">{{ $person->profile?->job_title }} · {{ $report['from'] }} to {{ $report['to'] }}. Hours count only closed shifts. An open day stays unsettled.</p>
  </div>
  <a class="btn" href="{{ route('portal.attendance.export', ['kind' => 'employee', 'user_id' => $person->id, 'from' => $from, 'to' => $to]) }}">Export CSV</a>
</header>
@include('portal.attendance.partials.tabs', ['selected' => $person, 'date' => $to, 'range' => $range])
<nav class="attendance-tabs" aria-label="Period">
  <a href="{{ route('portal.attendance.report', ['user_id' => $person->id, 'range' => '7']) }}" @if($range === '7') aria-current="page" @endif>7 days</a>
  <a href="{{ route('portal.attendance.report', ['user_id' => $person->id, 'range' => '30']) }}" @if($range === '30') aria-current="page" @endif>30 days</a>
  <a href="{{ route('portal.attendance.report', ['user_id' => $person->id, 'range' => 'month', 'date' => $to]) }}" @if($range === 'month') aria-current="page" @endif>This month</a>
</nav>
<form class="filters" method="get">
  <div class="field"><label for="user_id_pick">Employee</label>
    <select id="user_id_pick" name="user_id">@foreach ($people as $option)<option value="{{ $option->id }}" @selected($option->is($person))>{{ $option->name }}</option>@endforeach</select>
  </div>
  <div class="field"><label for="from">From</label><input id="from" name="from" type="date" value="{{ $from }}" required></div>
  <div class="field"><label for="to">To</label><input id="to" name="to" type="date" value="{{ $to }}" required></div>
  <button type="submit">Show</button>
</form>
@if ($errors->any())
  <div class="alert alert-bad">{{ $errors->first() }}</div>
@endif
<section class="grid stats">
  <article class="card stat"><strong>{{ $report['scheduled'] }}</strong><span>Scheduled days</span></article>
  <article class="card stat"><strong>{{ $report['worked'] }}</strong><span>Days worked</span></article>
  <article class="card stat"><strong>{{ $report['tracked_label'] }}</strong><span>Hours worked</span></article>
  <article class="card stat"><strong>{{ $report['late'] }}</strong><span>Late days</span></article>
</section>
<div class="attendance-split">
  <section class="card">
    <h2>Daily hours</h2>
    <p class="muted">Bar length compares recorded time with the assigned shift. Open days are marked unsettled and are not added to hours worked.</p>
    @forelse ($report['days'] as $entry)
      @if ($entry['scheduled_minutes'] > 0 || ($entry['minutes'] ?? 0) > 0)
        @php
          $scale = max($entry['scheduled_minutes'], (int) ($entry['minutes'] ?? 0), 60);
          $width = $entry['minutes'] === null ? 0 : min(100, (int) round(((int) $entry['minutes'] / $scale) * 100));
        @endphp
        <div class="hour-row">
          <span>{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d M') }}</span>
          <span class="hour-track"><span class="hour-fill {{ $entry['closed'] ? '' : 'is-open' }}" style="width: {{ $width }}%"></span></span>
          <span>{{ $entry['hours'] !== '' ? $entry['hours'] : '—' }}</span>
        </div>
      @endif
    @empty
      <div class="empty"><strong>No days in this period</strong></div>
    @endforelse
  </section>
  <section class="card">
    <h2>Period summary</h2>
    <p>Attendance rate <strong>{{ $report['rate'] === null ? '—' : $report['rate'].'%' }}</strong></p>
    <p>Average on a closed day <strong>{{ $report['average_label'] }}</strong></p>
    <p>Late days <strong>{{ $report['late'] }}</strong></p>
    <p>Approved leave <strong>{{ $report['leave'] }}</strong></p>
    <p>Missing punches <strong>{{ $report['missed'] }}</strong></p>
    <p>Unsettled days <strong>{{ $report['unsettled'] }}</strong></p>
    <p class="muted">Rate is present and late days divided by scheduled days. Weekly offs, holidays, approved leave, and a day that has not started yet are left out of that count.</p>
  </section>
</div>
<section class="card" style="margin-top:0.9rem">
  <h2>Attendance history</h2>
  @if ($report['days'] === [])
    <div class="empty"><strong>No attendance days</strong></div>
  @else
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Status</th><th>First in</th><th>Last out</th><th>Hours</th><th></th></tr></thead>
      <tbody>
        @foreach (array_reverse($report['days']) as $entry)
          <tr>
            <td data-label="Date">{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d M Y') }}</td>
            <td data-label="Status"><span class="badge badge-{{ match (true) { str_starts_with($entry['status'], 'Present') => 'good', str_starts_with($entry['status'], 'Late') => 'warn', str_starts_with($entry['status'], 'Absent') || str_starts_with($entry['status'], 'Missed') => 'bad', str_starts_with($entry['status'], 'On leave') => 'info', default => 'wait' } }}">{{ $entry['status'] }}</span></td>
            <td data-label="First in">{{ $entry['in'] !== '' ? $entry['in'] : '—' }}</td>
            <td data-label="Last out">{{ $entry['out'] !== '' ? $entry['out'] : '—' }}</td>
            <td data-label="Hours">{{ $entry['hours'] !== '' ? $entry['hours'] : '—' }}</td>
            <td data-label="Punches"><a href="{{ route('portal.attendance.report', ['user_id' => $person->id, 'from' => $from, 'to' => $to, 'day' => $entry['date']]) }}">View</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</section>
@if ($day !== '')
<section class="card" style="margin-top:0.9rem">
  <h2>Punches on {{ \Illuminate\Support\Carbon::parse($day)->format('d M Y') }}</h2>
  @forelse ($punches as $punch)
    <p>{{ \App\Support\PortalTime::format($punch->punched_at, $person, $punch->timezone) }} · {{ str_replace('_', ' ', $punch->punch_type) }} @if($punch->is_correction)<span class="badge">Correction</span>@endif @if($punch->superseded_at)<span class="badge">Replaced</span>@endif</p>
  @empty
    <div class="empty"><strong>No punches for this date</strong></div>
  @endforelse
</section>
@endif
@endsection
