@extends('portal.guest')
@section('title', 'Accept invitation')
@section('content')
<h1>Set your password</h1>
<p>Hello {{ $invitation->user?->name }}. You have been invited as {{ str_replace('_', ' ', $invitation->portal_role) }}. This link works once.</p>
@include('portal.partials.notice')
<form method="post" action="{{ route('portal.invitation.accept', $token) }}">
  @csrf
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required>
    <p class="help">At least 12 characters, with upper and lower case letters and a number.</p>
  </div>
  <div class="field">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" required>
  </div>
  <div class="field check">
    <input id="acknowledge" name="acknowledge" type="checkbox" value="1" required>
    <label for="acknowledge">I have read the notice about login, network, device, and attendance records.</label>
  </div>
  <button type="submit">Activate account</button>
</form>
@endsection
