@extends('portal.layout')
@section('title', 'Payslips')
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">Pay</p>
    <h1>Payslips</h1>
    <p class="muted">Published payslips for this account. A draft stays with payroll administration.</p>
  </div>
</header>
<section class="card">
  @forelse ($payslips as $payslip)
    <p><a href="{{ route('portal.payroll.show', $payslip) }}">{{ $payslip->period }}</a></p>
  @empty
    <div class="empty"><strong>No published payslip</strong><p>A payslip appears here after payroll publishes it.</p></div>
  @endforelse
</section>
@endsection
