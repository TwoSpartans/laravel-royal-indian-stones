@extends('portal.layout')
@section('title', 'Attendance exceptions')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Attendance</p>
    <h1>Exceptions and approvals</h1>
    <p class="muted">Approving a correction keeps the original punches and marks them replaced, then records the corrected times. Nothing here changes payroll.</p>
  </div>
</header>
@include('portal.attendance.partials.tabs', ['date' => $date !== '' ? $date : now()->toDateString()])
<form class="filters" method="get">
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status">
      @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field"><label for="q">Employee</label><input id="q" name="q" value="{{ $query }}" placeholder="Name"></div>
  <div class="field"><label for="date">Date</label><input id="date" name="date" type="date" value="{{ $date }}"></div>
  <button type="submit">Show</button>
</form>
<section class="card" id="queue">
  <h2>Waiting for a decision</h2>
  @forelse ($corrections as $correction)
    <article class="queue-item">
      <div>
        <strong>{{ $correction->user?->name }}</strong> · {{ $correction->work_date->format('d M Y') }} · Attendance correction · {{ str_replace('_', ' ', $correction->status) }}
        <p>Requested clock-in {{ $correction->requested_in ? substr((string) $correction->requested_in, 0, 5) : '—' }}, clock-out {{ $correction->requested_out ? substr((string) $correction->requested_out, 0, 5) : '—' }}</p>
        <p>{{ $correction->reason }}</p>
        <p class="muted">Original punches:
          @forelse ($originals[$correction->id] ?? [] as $punch)
            {{ \App\Support\PortalTime::format($punch->punched_at, $correction->user, $punch->timezone) }} {{ str_replace('_', ' ', $punch->punch_type) }}@if(! $loop->last), @endif
          @empty
            none recorded
          @endforelse
        </p>
        <p class="muted">No file was attached. The explanation above is the evidence on the request.</p>
      </div>
      @if ($status === 'pending' && ! auth()->user()->is($correction->user))
        <div class="actions">
          <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf<input type="hidden" name="decision" value="approved"><button type="submit">Approve</button></form>
          <form method="post" action="{{ route('portal.attendance.corrections.decide', $correction) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button></form>
        </div>
      @endif
    </article>
  @empty
  @endforelse
  @foreach ($overtime as $requestRow)
    <article class="queue-item">
      <div>
        <strong>{{ $requestRow->user?->name }}</strong> · {{ $requestRow->work_date->format('d M Y') }} · Overtime · {{ $requestRow->minutes }} minutes · {{ str_replace('_', ' ', $requestRow->status) }}
        <p>{{ $requestRow->reason }}</p>
      </div>
      @if ($status === 'pending' && ! auth()->user()->is($requestRow->user))
        <div class="actions">
          <form method="post" action="{{ route('portal.attendance.overtime.decide', $requestRow) }}">@csrf<input type="hidden" name="decision" value="approved"><button type="submit">Approve overtime</button></form>
          <form method="post" action="{{ route('portal.attendance.overtime.decide', $requestRow) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button></form>
        </div>
      @endif
    </article>
  @endforeach
  @if ($corrections->isEmpty() && $overtime->isEmpty())
    <div class="empty"><strong>No correction or overtime requests</strong></div>
  @endif
  @if ($status !== 'pending')
    <p class="muted">Already decided</p>
  @endif
</section>
@endsection
