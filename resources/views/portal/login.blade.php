@extends('portal.auth')
@section('title', 'Sign in')
@section('content')
<h1>Sign in</h1>
<p class="auth-lead">Use the work email issued for your staff account.</p>
@if (session('status'))
  <p class="auth-status" role="status">{{ session('status') }}</p>
@endif
@error('email')
  <p class="auth-error" role="alert">{{ $message }}</p>
@enderror
<form method="post" action="{{ route('portal.login.store') }}">
  @csrf
  <div class="field">
    <label for="email">Work email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <div class="auth-tools">
    <label class="check" for="show-password">
      <input id="show-password" type="checkbox">
      <span>Show password</span>
    </label>
    <a href="{{ route('portal.password.request') }}">Forgot password</a>
  </div>
  <button class="auth-submit" type="submit">Sign in</button>
</form>
<p class="auth-note">This page is not part of the public catalogue. <a href="{{ route('portal.privacy') }}">Read the full data notice</a>.</p>
<script>
document.getElementById('show-password').addEventListener('change', function () {
  var password = document.getElementById('password');
  password.type = this.checked ? 'text' : 'password';
  password.focus();
});
</script>
@endsection
