@extends('portal.guest')
@section('title', 'Authenticator')
@section('content')
<h1>Set up the authenticator</h1>
<p>Employer and Admin / HR accounts need a second step. Add this key to an authenticator app, then enter the 6-digit code.</p>
<p class="secret">{{ $secret }}</p>
<p class="help">Or copy this setup link into the app: <span class="secret">{{ $uri }}</span></p>
<form method="post" action="{{ route('portal.mfa.confirm') }}">
  @csrf
  <div class="field">
    <label for="code">Authentication code</label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
  </div>
  <button type="submit">Confirm authenticator</button>
</form>
@endsection
