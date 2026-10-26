@extends('portal.layout')
@section('title', 'Payroll records')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Payroll-ready records</p>
    <h1>Salary inputs</h1>
    <p class="muted">{{ $calculationsEnabled ? 'A reviewer has allowed basic calculations.' : 'Calculations are withheld until a qualified reviewer enables them.' }} No salary is marked sent.</p>
  </div>
</header>
@if (auth()->user()->portal_role?->canAdminister())
  <section class="card" style="margin-top:0.9rem">
    <h2>Salary structure</h2>
    <form method="post" action="{{ route('portal.payroll.structures.store') }}" class="form-grid">
      @csrf
      <div class="field"><label for="user_id">Person</label><select id="user_id" name="user_id" required>@foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="field"><label for="effective_on">Effective</label><input id="effective_on" name="effective_on" type="date" required></div>
      <div class="field"><label for="basic">Basic, rupees</label><input id="basic" name="basic" required></div>
      <div class="field"><label for="hra">HRA, rupees</label><input id="hra" name="hra"></div>
      <div class="field"><label for="special">Special allowance, rupees</label><input id="special" name="special"></div>
      <div class="field"><label for="notes">Notes</label><input id="notes" name="notes"></div>
      <button type="submit">Save structure</button>
    </form>
  </section>
  <section class="card" style="margin-top:0.9rem">
    <h2>Approved adjustment</h2>
    <form method="post" action="{{ route('portal.payroll.adjustments.store') }}" class="form-grid">
      @csrf
      <div class="field"><label for="adjust_user">Person</label><select id="adjust_user" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="field"><label for="period">Period</label><input id="period" name="period" placeholder="2026-10" required></div>
      <div class="field"><label for="amount">Amount, rupees</label><input id="amount" name="amount" required></div>
      <div class="field"><label for="reason">Reason</label><input id="reason" name="reason" required></div>
      <button type="submit">Record adjustment</button>
    </form>
  </section>
  <section class="card" style="margin-top:0.9rem">
    <h2>Prepare a period</h2>
    <form method="post" action="{{ route('portal.payroll.prepare') }}" class="form-grid">
      @csrf
      <div class="field"><label for="prepare_user">Person</label><select id="prepare_user" name="user_id">@foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="field"><label for="prepare_period">Period</label><input id="prepare_period" name="period" value="{{ now()->format('Y-m') }}" required></div>
      <button type="submit">Prepare</button>
    </form>
  </section>
@endif
<section class="card" style="margin-top:0.9rem">
  <h2>Records</h2>
  @forelse ($people as $person)
    <h3>{{ $person->name }}</h3>
    @php $structure = ($structures[$person->id] ?? collect())->first(); @endphp
    <p>{{ $structure ? 'Structure from '.$structure->effective_on->format('d M Y') : 'No salary structure' }}</p>
    @forelse (($payslips[$person->id] ?? collect()) as $payslip)
      @if (auth()->user()->portal_role?->canAdminister() || $payslip->status === 'published')
        <div class="record-line"><a href="{{ route('portal.payroll.show', $payslip) }}">{{ $payslip->period }}</a> · {{ str_replace('_', ' ', $payslip->status) }} · payment {{ str_replace('_', ' ', $payslip->payment_state) }}
          @if (auth()->user()->portal_role?->canAdminister() && $payslip->status !== 'published')
            <form method="post" action="{{ route('portal.payroll.publish', $payslip) }}">@csrf<button type="submit">Publish to employee</button></form>
          @endif
        </div>
      @endif
    @empty
      <div class="empty"><strong>No payslip</strong></div>
    @endforelse
  @empty
    <div class="empty"><strong>No staff records</strong></div>
  @endforelse
</section>
@endsection
