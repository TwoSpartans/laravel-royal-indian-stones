@extends('portal.guest')
@section('title', 'Verification code')
@section('content')
<h1>Enter the verification code</h1>
<p class="muted">Use the 6-digit authenticator code or one recovery code.</p>
<form method="post" action="{{ route('portal.mfa.verify') }}">
  @csrf
  <div class="field">
    <label for="code">Code</label>
    <input id="code" name="code" autocomplete="one-time-code" required autofocus>
  </div>
  <button type="submit">Continue</button>
</form>
@endsection
