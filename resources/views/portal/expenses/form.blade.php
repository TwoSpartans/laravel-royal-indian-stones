@extends('portal.layout')
@section('title', 'New expense')
@section('content')
<header class="page-head"><div><p class="eyebrow">Reimbursements</p><h1>New expense claim</h1></div></header>
<form method="post" action="{{ route('portal.expenses.store') }}" enctype="multipart/form-data" class="form-grid">
  @csrf
  <div class="field"><label for="category">Category</label><select id="category" name="category" required>@foreach (config('portal.expense_categories') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="amount">Amount in rupees</label><input id="amount" name="amount" inputmode="decimal" required></div>
  <div class="field"><label for="incurred_on">Date</label><input id="incurred_on" name="incurred_on" type="date" required></div>
  <div class="field span-2"><label for="description">What was it for?</label><textarea id="description" name="description" required></textarea></div>
  <div class="field"><label for="receipt">Receipt</label><input id="receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
  <button type="submit">Submit claim</button>
</form>
@endsection
