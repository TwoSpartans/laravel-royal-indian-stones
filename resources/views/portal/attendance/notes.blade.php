@extends('portal.layout')
@section('title', 'Shift notes')
@section('content')
@php
  $filters = array_filter([
    'date' => $date,
    'q' => $query,
    'department_id' => $departmentId ?: null,
    'shift_id' => $shiftId ?: null,
    'location_id' => $locationId ?: null,
  ]);
@endphp
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>Shift notes</h1>
    <p class="muted">Each card is one punch. In and out stay separate, with the answers written at that moment. Times use {{ $timezone }}.</p>
  </div>
</header>
@include('portal.attendance.partials.tabs')
<form class="filters att-filters" method="get">
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
@if ($events->isEmpty())
  <div class="empty"><strong>No punches on this date</strong><p>Notes appear here after someone clocks in or out.</p></div>
@else
  <div class="punch-board">
    @foreach ($events as $event)
      <section class="card punch-card">
        @include('portal.attendance.partials.punch-note', ['punch' => $event])
      </section>
    @endforeach
  </div>
@endif
@endsection
