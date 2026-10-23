@extends('portal.guest')
@section('title', 'Sign in')
@section('content')
<h1>Sign in</h1>
<p class="muted">Private staff access for Royal Indian Stones. This page is not part of the public catalogue.</p>
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
  <button type="submit">Sign in</button>
</form>
<p><a href="{{ route('portal.password.request') }}">Forgot password</a></p>
@include('portal.partials.notice')
<p class="help"><a href="{{ route('portal.privacy') }}">Read the full data notice</a>. Accounts are created by the employer or HR. There is no public registration.</p>
@endsection
