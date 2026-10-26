<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Sign in') | Royal Indian Stones</title>
<link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,640&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/portal.css') }}?v=19" rel="stylesheet">
</head>
<body class="portal auth-body">
<a class="skip" href="#portal-main">Skip to content</a>
<div class="help-bar">@include('portal.partials.how-to-use')</div>
<main class="auth-screen" id="portal-main">
  <section class="auth-panel">
    <div class="auth-aside">
      <a class="brand" href="{{ url('/') }}">
        <img src="{{ asset('assets/img/favicon.png') }}" alt="">
        <span><strong>Royal Indian Stones</strong><span>Staff portal</span></span>
      </a>
      <p class="auth-aside-lead">Private access for the people who quarry, finish, and ship the stone.</p>
      <ul>
        <li>Accounts are created by the employer or HR.</li>
        <li>Signing in does not mark you present.</li>
        <li>Network and device records stay off the public site.</li>
      </ul>
    </div>
    <div class="auth-form">
      @yield('content')
    </div>
  </section>
</main>
</body>
</html>
