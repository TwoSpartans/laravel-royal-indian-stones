<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Staff portal') | Royal Indian Stones</title>
<link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/portal.css') }}?v=1" rel="stylesheet">
</head>
<body class="portal">
<a class="skip" href="#portal-main">Skip to content</a>
<div class="guest">
  <main class="guest-card" id="portal-main">
    <a class="brand" href="{{ url('/') }}">
      <img src="{{ asset('assets/img/favicon.png') }}" alt="">
      <span><strong>Royal Indian Stones</strong><span>Staff portal</span></span>
    </a>
    @include('portal.partials.alerts')
    @yield('content')
  </main>
</div>
</body>
</html>
