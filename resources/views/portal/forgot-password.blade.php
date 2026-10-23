@extends('portal.guest')
@section('title', 'Reset password')
@section('content')
<h1>Reset password</h1>
<p class="muted">If the email belongs to a staff account, we will send a reset link.</p>
<form method="post" action="{{ route('portal.password.email') }}">
  @csrf
  <div class="field">
    <label for="email">Work email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
  </div>
  <button type="submit">Send reset link</button>
</form>
@endsection
