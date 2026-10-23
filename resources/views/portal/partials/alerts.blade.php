@if (session('status'))
  <div class="alert alert-good" role="status">{{ session('status') }}</div>
@endif
@if (session('invitation_url'))
  <div class="alert" role="status">
    <strong>One-time invitation link</strong>
    <p class="help">Shown only to the person who created the account. Copy it if email is not delivered yet.</p>
    <p class="secret"><a href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a></p>
  </div>
@endif
@if (session('recovery_codes'))
  <div class="alert" role="status">
    <strong>Save these recovery codes now.</strong>
    <p class="help">Each code works once if the authenticator is unavailable. They will not be shown again.</p>
    <ul>
      @foreach (session('recovery_codes') as $code)
        <li><code>{{ $code }}</code></li>
      @endforeach
    </ul>
  </div>
@endif
@if ($errors->any())
  <div class="alert alert-bad" role="alert">
    <strong>Please correct the form.</strong>
    <ul>
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
