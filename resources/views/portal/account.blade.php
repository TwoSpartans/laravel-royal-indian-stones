@extends('portal.layout')
@section('title', 'My account')
@section('content')
<header class="page-head"><div><p class="eyebrow">{{ $person->profile?->employee_code }}</p><h1>{{ $person->name }}</h1><p class="muted">{{ $person->profile?->job_title }} · {{ \App\Support\PortalTime::timezone($person) }}</p></div></header>
<section class="card">
  <h2>Password</h2>
  <form method="post" action="{{ route('portal.account.password') }}">
    @csrf
    <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
    <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>
    <div class="field"><label for="password_confirmation">Confirm</label><input id="password_confirmation" name="password_confirmation" type="password" required></div>
    <button type="submit">Update password</button>
  </form>
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Devices</h2>
  @forelse ($person->devices as $device)
    <p>{{ $device->label }} · {{ $device->status }} · last seen {{ $device->last_seen_at ? \App\Support\PortalTime::format($device->last_seen_at, $person) : '—' }}</p>
  @empty
    <div class="empty"><strong>No device registered on this account yet</strong></div>
  @endforelse
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>Published payslips</h2>
  @forelse ($payslips as $payslip)
    <p><a href="{{ route('portal.payroll.show', $payslip) }}">{{ $payslip->period }}</a></p>
  @empty
    <div class="empty"><strong>No published payslip</strong></div>
  @endforelse
</section>
<section class="card" style="margin-top:0.9rem">
  <h2>My documents</h2>
  @forelse ($documents as $document)
    <p><a href="{{ route('portal.documents.download', $document) }}">{{ $document->title }}</a></p>
  @empty
    <div class="empty"><strong>No documents shared with you</strong></div>
  @endforelse
</section>
@endsection
