@extends('portal.layout')
@section('title', 'Compare attendance')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>Team comparison</h1>
    <p class="muted">{{ $from }} to {{ $to }}. Rates use scheduled days, not raw hours. Weekly offs, holidays, and approved leave stay out of the rate.</p>
  </div>
  @if ($chosen->count() >= 2)
    <a class="btn" href="{{ route('portal.attendance.export', array_merge(['kind' => 'compare', 'from' => $from, 'to' => $to], ['employees' => $chosen->modelKeys()])) }}">Export CSV</a>
  @endif
</header>
@include('portal.attendance.partials.tabs', ['date' => $to, 'range' => $range])
<nav class="attendance-tabs" aria-label="Period">
  <a href="{{ route('portal.attendance.compare', ['range' => '7', 'employees' => $chosen->modelKeys()]) }}" @if($range === '7') aria-current="page" @endif>7 days</a>
  <a href="{{ route('portal.attendance.compare', ['range' => '30', 'employees' => $chosen->modelKeys()]) }}" @if($range === '30') aria-current="page" @endif>30 days</a>
  <a href="{{ route('portal.attendance.compare', ['range' => 'month', 'employees' => $chosen->modelKeys()]) }}" @if($range === 'month') aria-current="page" @endif>This month</a>
</nav>
<form class="filters" method="get">
  <div class="field"><label for="from">From</label><input id="from" name="from" type="date" value="{{ $from }}" required></div>
  <div class="field"><label for="to">To</label><input id="to" name="to" type="date" value="{{ $to }}" required></div>
  <fieldset class="span-2">
    <legend>People, two to four</legend>
    @foreach ($people as $option)
      <label class="check" for="employee-{{ $option->id }}"><input id="employee-{{ $option->id }}" type="checkbox" name="employees[]" value="{{ $option->id }}" @checked($chosen->contains('id', $option->id))><span>{{ $option->name }}</span></label>
    @endforeach
  </fieldset>
  <button type="submit">Compare</button>
</form>
@if ($errors->any())
  <div class="alert alert-bad">{{ $errors->first() }}</div>
@endif
@if ($chosen->count() < 2)
  <div class="empty"><strong>Choose at least two people</strong><p>The company needs two people on the roster before a comparison can be shown.</p></div>
@else
<section class="card">
  <h2>Side by side</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><a href="{{ route('portal.attendance.compare', ['from' => $from, 'to' => $to, 'employees' => $chosen->modelKeys(), 'sort' => 'name']) }}">Employee</a></th>
          <th>Worked / scheduled</th>
          <th><a href="{{ route('portal.attendance.compare', ['from' => $from, 'to' => $to, 'employees' => $chosen->modelKeys(), 'sort' => 'rate', 'dir' => 'desc']) }}">Attendance</a></th>
          <th><a href="{{ route('portal.attendance.compare', ['from' => $from, 'to' => $to, 'employees' => $chosen->modelKeys(), 'sort' => 'hours', 'dir' => 'desc']) }}">Hours worked</a></th>
          <th><a href="{{ route('portal.attendance.compare', ['from' => $from, 'to' => $to, 'employees' => $chosen->modelKeys(), 'sort' => 'late', 'dir' => 'desc']) }}">Late days</a></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          <tr>
            <td data-label="Employee"><a href="{{ route('portal.attendance.report', ['user_id' => $row['user']->id, 'from' => $from, 'to' => $to]) }}">{{ $row['user']->name }}</a></td>
            <td data-label="Worked / scheduled">{{ $row['worked'] }} / {{ $row['scheduled'] }}</td>
            <td data-label="Attendance">{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</td>
            <td data-label="Hours worked">{{ $row['tracked_label'] }}</td>
            <td data-label="Late days">{{ $row['late'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Attendance rate</h2>
  @foreach ($rows as $row)
    <div class="hour-row">
      <span>{{ $row['user']->name }}</span>
      <span class="hour-track"><span class="hour-fill" style="width: {{ min(100, (float) ($row['rate'] ?? 0)) }}%"></span></span>
      <span>{{ $row['rate'] === null ? '—' : $row['rate'].'%' }}</span>
    </div>
  @endforeach
  <p class="muted">A higher bar means more scheduled days were present or late. It does not rank people by hours alone.</p>
</section>
@endif
@endsection
