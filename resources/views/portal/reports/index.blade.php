@extends('portal.layout')
@section('title', 'Reports')
@section('content')
<header class="page-head"><div><p class="eyebrow">Reports</p><h1>Export a staff report</h1><p class="muted">Managers receive their team. Employees cannot export workforce reports.</p></div></header>
<form method="post" action="{{ route('portal.reports.export') }}" class="form-grid">
  @csrf
  <div class="field"><label for="type">Report</label>
    <select id="type" name="type" required>
      <option value="attendance">Attendance</option>
      <option value="leave">Leave</option>
      <option value="overtime">Overtime</option>
      <option value="reimbursements">Reimbursements</option>
      @if (auth()->user()->portal_role?->canAdminister())
        <option value="headcount">Headcount</option>
      @endif
      <option value="tasks">Task completion</option>
    </select>
  </div>
  <div class="field"><label for="format">File</label><select id="format" name="format"><option value="csv">CSV</option><option value="pdf">PDF</option></select></div>
  <div class="field"><label for="from">From</label><input id="from" name="from" type="date" required></div>
  <div class="field"><label for="to">To</label><input id="to" name="to" type="date" required></div>
  <button type="submit">Download</button>
</form>
@endsection
