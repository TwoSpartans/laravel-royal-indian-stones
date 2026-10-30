@php
  $role = auth()->user()?->portal_role;
  $isAdmin = $role?->canAdminister() === true;
  $isManager = $isAdmin;
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
  $sectionForLink = [
    'portal.attendance.index' => 'attendance',
    'portal.leave.index' => 'leave',
    'portal.tasks.index' => 'tasks',
    'portal.expenses.index' => 'expenses',
  ];
  $links = array_values(array_filter($links, function (array $link) use ($sectionForLink): bool {
    $section = $sectionForLink[$link[0]] ?? null;

    return $section === null || auth()->user()?->canOpenPortalSection($section);
  }));
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
<link href="{{ asset('assets/css/portal.css') }}?v=31" rel="stylesheet">
</head>
<body class="portal">
<a class="skip" href="#portal-main">Skip to content</a>
<div class="portal-shell">
  <div class="nav-backdrop" data-nav-close></div>
  <aside class="portal-side" id="portal-nav">
    <button type="button" class="nav-close" data-nav-close aria-label="Close menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <a class="brand" href="{{ route('portal.home') }}">
      <img src="{{ asset('assets/img/favicon.png') }}" alt="">
      <span><strong>Royal Indian Stones</strong><span>Staff portal</span></span>
    </a>
    <nav aria-label="Staff">
      @php
        $groups = ['My day' => [], 'Work' => [], 'Company' => []];
        foreach ($links as $link) {
            $group = match ($link[0]) {
                'portal.home', 'portal.attendance.index', 'portal.leave.index' => 'My day',
                'portal.tasks.index', 'portal.expenses.index' => 'Work',
                default => 'Company',
            };
            $groups[$group][] = $link;
        }
      @endphp
      @foreach ($groups as $group => $items)
        @if ($items !== [])
          <p class="nav-label">{{ $group }}</p>
          @foreach ($items as [$route, $label, $icon])
            <a class="side-link" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>
              <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
            </a>
          @endforeach
        @endif
      @endforeach
      <p class="nav-label">Account</p>
      <a class="side-link" href="{{ route('portal.account') }}" @if(request()->routeIs('portal.account')) aria-current="page" @endif><i class="bi bi-person" aria-hidden="true"></i> My account</a>
      <a class="side-link" href="{{ route('portal.notifications') }}" @if(request()->routeIs('portal.notifications')) aria-current="page" @endif><i class="bi bi-bell" aria-hidden="true"></i> Notices</a>
      @if (auth()->user()?->is_site_admin)
        <a class="side-link side-studio" href="{{ route('admin.dashboard') }}"><i class="bi bi-columns-gap" aria-hidden="true"></i> Content studio</a>
      @endif
    </nav>
  </aside>
  <div class="portal-main">
    <header class="topbar">
      <button type="button" class="nav-toggle" data-nav-open aria-expanded="false" aria-controls="portal-nav" aria-label="Open menu">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <a class="topbar-brand" href="{{ route('portal.home') }}">Royal Indian Stones</a>
      <div class="account-menu">
        <button type="button" class="account-toggle" aria-expanded="false" aria-haspopup="true" aria-label="Account menu">
          <i class="bi bi-person" aria-hidden="true"></i>
        </button>
        <div class="account-panel">
          <p class="who"><strong>{{ auth()->user()->name }}</strong><span>{{ $role?->label() }}</span></p>
          @if (auth()->user()?->is_site_admin)
            <a class="switch-app" href="{{ route('admin.dashboard') }}">Content studio</a>
          @endif
          <form method="post" action="{{ route('portal.logout') }}">
            @csrf
            <button class="btn-secondary" type="submit">Sign out</button>
          </form>
        </div>
      </div>
    </header>
    <main id="portal-main">
      <div class="page-help">@include('portal.partials.how-to-use')</div>
      @include('portal.partials.alerts')
      @yield('content')
    </main>
  </div>
</div>
<nav class="tabbar" aria-label="Primary">
  @foreach ($links as [$route, $label, $icon])
    <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>
      <i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}
    </a>
  @endforeach
</nav>
<script>
(() => {
  const root = document.body;
  const openButton = document.querySelector('[data-nav-open]');
  const setOpen = (open) => {
    root.classList.toggle('nav-open', open);
    openButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
    openButton?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    const icon = openButton?.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-list', !open);
      icon.classList.toggle('bi-x-lg', open);
    }
  };
  const helpButton = document.querySelector('.help-open');
  const homeTools = document.querySelector('#portal-main .home-tools');
  const pageHead = document.querySelector('#portal-main .page-head');
  if (helpButton && homeTools) {
    (homeTools.querySelector('form') ?? homeTools).append(helpButton);
  } else if (helpButton && pageHead) {
    pageHead.append(helpButton);
  }
  const account = document.querySelector('.account-menu');
  const accountButton = account?.querySelector('.account-toggle');
  accountButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    const open = account.classList.toggle('is-open');
    accountButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', (event) => {
    if (account && !account.contains(event.target)) {
      account.classList.remove('is-open');
      accountButton?.setAttribute('aria-expanded', 'false');
    }
  });
  openButton?.addEventListener('click', () => setOpen(!root.classList.contains('nav-open')));
  document.querySelectorAll('[data-nav-close], .portal-side a').forEach((el) => {
    el.addEventListener('click', () => setOpen(false));
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
})();
</script>
</body>
</html>
