@extends('portal.layout')
@section('title', 'Payslip '.$payslip->period)
@section('content')
<header class="page-head">
  <div>
    <p class="eyebrow">{{ $owner->name }}</p>
    <h1>{{ $payslip->period }}</h1>
  </div>
</header>
<section class="card">
  <p class="badge">{{ str_replace('_', ' ', $payslip->status) }}</p>
  <p>{{ $payslip->disclaimer }}</p>
  <p>Present days: {{ $payslip->summary['present_days'] ?? '—' }}</p>
  <p>Absent days: {{ $payslip->summary['absent_days'] ?? '—' }}</p>
  <p>Late days: {{ $payslip->summary['late_days'] ?? '—' }}</p>
  <p>Approved overtime minutes: {{ $payslip->summary['overtime_minutes'] ?? 0 }}</p>
  <p>Approved adjustments: {{ \App\Support\Money::format((int) ($payslip->summary['adjustment_paise'] ?? 0)) }}</p>
  <p>Calculations: {{ $payslip->summary['calculations'] ?? 'withheld' }}</p>
  <p>Net: {{ $payslip->summary['net_paise'] === null ? 'Not calculated' : \App\Support\Money::format((int) $payslip->summary['net_paise']) }}</p>
  <p>Payment record: {{ str_replace('_', ' ', $payslip->payment_state) }}. This is not a bank payout.</p>
</section>
@endsection
