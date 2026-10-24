@extends('portal.guest')
@section('title', 'Choose a password')
@section('content')
<h1>Choose a new password</h1>
<form method="post" action="{{ route('portal.password.update') }}">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="field">
    <label for="email">Work email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required>
  </div>
  <div class="field">
    <label for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required>
    <p class="help">At least 8 characters, with upper and lower case letters and a number.</p>
  </div>
  <div class="field">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
  </div>
  <button type="submit">Update password</button>
</form>
@endsection
