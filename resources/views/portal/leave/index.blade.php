@extends('portal.layout')
@section('title', 'Leave')
@section('content')
<header class="page-head"><div><p class="eyebrow">Leave</p><h1>Requests and team calendar</h1></div></header>
@if (auth()->user()->recordsOwnAttendance())
<section class="card">
  <h2>Your balance</h2>
  @if ($balances->isEmpty())
    <div class="empty"><strong>No balance for this year</strong></div>
  @else
    <div class="balance-list">
      @foreach ($balances as $balance)
        <span>{{ $balance->type?->name }}: {{ $balance->remaining() }} left</span>
      @endforeach
    </div>
  @endif
  <form method="post" action="{{ route('portal.leave.store') }}" class="form-grid">
    @csrf
    <div class="field"><label for="leave_type_id">Type</label><select id="leave_type_id" name="leave_type_id" required>@foreach ($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
    <div class="field"><label for="starts_on">From</label><input id="starts_on" name="starts_on" type="date" required></div>
    <div class="field"><label for="ends_on">To</label><input id="ends_on" name="ends_on" type="date" required></div>
    <div class="field span-2"><label for="reason">Reason</label><textarea id="reason" name="reason" required></textarea></div>
  <button type="submit">Submit request</button>
</form>
</section>
@endif
<form class="filters" method="get">
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status">
      <option value="">All</option>
      @foreach (['pending_manager' => 'Waiting for manager', 'pending_hr' => 'Waiting for HR', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <button type="submit">Filter</button>
</form>
<div class="table-wrap">
  @if ($requests->isEmpty())
    <div class="empty"><strong>No leave requests</strong><p>{{ auth()->user()->recordsOwnAttendance() ? 'Apply above. Weekly offs and holidays are not counted as leave days.' : 'Employee, manager, and Admin / HR requests appear here. The employer account does not apply for leave.' }}</p></div>
  @else
    <table>
      <thead><tr><th>Person</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach ($requests as $leave)
          <tr>
            <td data-label="Person">{{ $leave->user?->name }}</td>
            <td data-label="Type">{{ $leave->type?->name }}</td>
            <td data-label="Dates">{{ $leave->starts_on->format('d M') }} – {{ $leave->ends_on->format('d M Y') }}</td>
            <td data-label="Days">{{ $leave->days }}</td>
            <td data-label="Status"><span class="badge">{{ str_replace('_', ' ', $leave->status) }}</span></td>
            <td data-label="Decision">
              @if (in_array($leave->status, ['pending_manager', 'pending_hr'], true) && ! auth()->user()->is($leave->user))
                <div class="actions">
                  <form method="post" action="{{ route('portal.leave.decide', $leave) }}">@csrf<input type="hidden" name="decision" value="approved"><button type="submit">Approve</button></form>
                  <form method="post" action="{{ route('portal.leave.decide', $leave) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn-secondary" type="submit">Reject</button></form>
                </div>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
<section class="card" style="margin-top:0.9rem">
  <h2>Calendar for {{ $month }}</h2>
  @forelse ($calendar as $leave)
    <p>{{ $leave->user?->name }} · {{ $leave->type?->name }} · {{ $leave->starts_on->format('d M') }} – {{ $leave->ends_on->format('d M') }} · {{ str_replace('_', ' ', $leave->status) }}</p>
  @empty
    <div class="empty"><strong>Nothing on the calendar</strong></div>
  @endforelse
</section>
@endsection
