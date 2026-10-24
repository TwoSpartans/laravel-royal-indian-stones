@extends('portal.layout')
@section('title', 'Expenses')
@section('content')
<header class="page-head">
  <div><p class="eyebrow">Reimbursements</p><h1>Expenses</h1></div>
  <a class="btn" href="{{ route('portal.expenses.create') }}">New claim</a>
</header>
<form class="filters" method="get">
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status">
      <option value="">All</option>
      @foreach (['submitted' => 'Waiting for manager', 'pending_hr' => 'Waiting for HR', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <button type="submit">Filter</button>
</form>
<div class="table-wrap">
  @if ($claims->isEmpty())
    <div class="empty"><strong>No claims</strong><p>Submit a receipt for yard supplies, samples, travel, or export paperwork.</p></div>
  @else
    <table>
      <thead><tr><th>Person</th><th>When</th><th>Amount</th><th>Status</th><th>Payment record</th></tr></thead>
      <tbody>
        @foreach ($claims as $claim)
          <tr>
            <td data-label="Person"><a href="{{ route('portal.expenses.show', $claim) }}">{{ $claim->user?->name }}</a></td>
            <td data-label="When">{{ $claim->incurred_on->format('d M Y') }}</td>
            <td data-label="Amount">{{ \App\Support\Money::format($claim->amount_paise) }}</td>
            <td data-label="Status">{{ str_replace('_', ' ', $claim->status) }}</td>
            <td data-label="Payment record">{{ str_replace('_', ' ', $claim->payment_state) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
