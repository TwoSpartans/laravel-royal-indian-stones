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
<form method="post" action="{{ route('portal.attendance.clock') }}" class="btn-row">
  @csrf
  <button class="clock-btn" name="punch_type" value="in">Clock in</button>
  <button class="clock-btn btn-secondary" name="punch_type" value="break_start">Start break</button>
  <button class="clock-btn btn-secondary" name="punch_type" value="break_end">End break</button>
  <button class="clock-btn" name="punch_type" value="out">Clock out</button>
</form>
<form class="filters" method="get">
  <div class="field"><label for="date">Date</label><input id="date" name="date" type="date" value="{{ $date }}"></div>
  <div class="field"><label for="user_id">Person</label>
    <select id="user_id" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}" @selected($selected->is($person))>{{ $person->name }}</option>@endforeach</select>
  </div>
  <button type="submit">Show</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Person</th><th>Status</th><th>First in</th><th>Last out</th></tr></thead>
    <tbody>
      @foreach ($roster as $row)
        <tr>
          <td data-label="Person">{{ $row['user']->name }}</td>
          <td data-label="Status"><span class="badge">{{ $row['label'] }}</span></td>
          <td data-label="First in">{{ $row['first_in'] ? \App\Support\PortalTime::format($row['first_in']->punched_at, $row['user'], $row['first_in']->timezone) : '—' }}</td>
          <td data-label="Last out">{{ $row['last_out'] ? \App\Support\PortalTime::format($row['last_out']->punched_at, $row['user'], $row['last_out']->timezone) : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
<section class="card" style="margin-top:0.9rem">
  <h2>Recent punches for {{ $selected->name }}</h2>
  @forelse ($punches as $punch)
    <p>{{ \App\Support\PortalTime::format($punch->punched_at, $selected, $punch->timezone) }} · {{ str_replace('_', ' ', $punch->punch_type) }} @if($punch->is_correction)<span class="badge">Correction</span>@endif</p>
  @empty
    <div class="empty"><strong>No punches yet</strong><p>Use clock in when the shift starts.</p></div>
  @endforelse
</section>
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
<section class="card" style="margin-top:0.9rem">
  <h2>Corrections and overtime</h2>
  @foreach ($corrections as $correction)
    <p>{{ $correction->user?->name }} · {{ $correction->work_date->format('d M Y') }} · {{ $correction->status }}
      @if ($correction->status === 'pending' && ! auth()->user()->is($correction->user))
        <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf
          <input type="hidden" name="decision" value="approved"><button type="submit">Approve</button>
        </form>
        <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf
          <input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button>
        </form>
      @endif
    </p>
  @endforeach
  @foreach ($overtime as $requestRow)
    <p>{{ $requestRow->user?->name }} · {{ $requestRow->minutes }} min · {{ $requestRow->status }}
      @if ($requestRow->status === 'pending' && ! auth()->user()->is($requestRow->user))
        <form method="post" action="{{ route('portal.attendance.overtime.decide', $requestRow) }}">@csrf
          <input type="hidden" name="decision" value="approved"><button type="submit">Approve overtime</button>
        </form>
      @endif
    </p>
  @endforeach
  @if ($corrections->isEmpty() && $overtime->isEmpty())
    <div class="empty"><strong>No correction or overtime requests</strong></div>
  @endif
</section>
@endsection
