@php
  $role = auth()->user()?->portal_role;
  $isAdmin = in_array($role, [\App\Enums\PortalRole::Employer, \App\Enums\PortalRole::AdminHr], true);
  $isManager = $role === \App\Enums\PortalRole::Manager;
  $links = [
    ['portal.home', 'Home', 'bi-house'],
    ['portal.attendance.index', 'Attendance', 'bi-clock'],
    ['portal.leave.index', 'Leave', 'bi-calendar3'],
    ['portal.tasks.index', 'Tasks', 'bi-list-check'],
    ['portal.expenses.index', 'Expenses', 'bi-receipt'],
  ];
  if ($isAdmin || $isManager) {
    $links[] = ['portal.people.index', 'People', 'bi-people'];
    $links[] = ['portal.reports.index', 'Reports', 'bi-bar-chart'];
  }
  if ($isAdmin) {
    $links[] = ['portal.payroll.index', 'Payroll', 'bi-wallet2'];
    $links[] = ['portal.workplace.index', 'Workplace', 'bi-geo-alt'];
  }
@endphp
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
<div class="portal-shell">
  <aside class="portal-side">
    <a class="brand" href="{{ route('portal.home') }}">
      <img src="{{ asset('assets/img/favicon.png') }}" alt="">
      <span><strong>Royal Indian Stones</strong><span>Staff portal</span></span>
    </a>
    <nav aria-label="Staff">
      @foreach ($links as [$route, $label, $icon])
        <a class="side-link" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>
          <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
        </a>
      @endforeach
      <a class="side-link" href="{{ route('portal.account') }}"><i class="bi bi-person" aria-hidden="true"></i> My account</a>
      <a class="side-link" href="{{ route('portal.notifications') }}"><i class="bi bi-bell" aria-hidden="true"></i> Notices</a>
    </nav>
  </aside>
  <div class="portal-main">
    <header class="topbar">
      <details class="more-menu">
        <summary>Menu</summary>
        <div>
          @foreach ($links as [$route, $label, $icon])
            <a href="{{ route($route) }}">{{ $label }}</a>
          @endforeach
          <a href="{{ route('portal.account') }}">My account</a>
          <a href="{{ route('portal.notifications') }}">Notices</a>
        </div>
      </details>
      <div class="who">
        <strong>{{ auth()->user()->name }}</strong>
        <span>{{ $role?->label() }}</span>
      </div>
      <form method="post" action="{{ route('portal.logout') }}">
        @csrf
        <button class="btn-secondary" type="submit">Sign out</button>
      </form>
    </header>
    <main id="portal-main">
      @include('portal.partials.alerts')
      @yield('content')
    </main>
  </div>
</div>
<nav class="tabbar" aria-label="Primary">
  @foreach (array_slice($links, 0, 5) as [$route, $label, $icon])
    <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>
      <i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}
    </a>
  @endforeach
</nav>
</body>
</html>
