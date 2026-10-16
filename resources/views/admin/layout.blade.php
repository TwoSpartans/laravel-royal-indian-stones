<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ trim($__env->yieldContent('pageTitle')) ?: 'Admin' }} | Royal Indian Stones</title>
<link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/admin.css') }}" rel="stylesheet">
</head>
<body class="admin-shell">
<script>
if (window.matchMedia('(min-width: 992px)').matches) {
  try {
    if (window.localStorage.getItem('admin-sidebar-open') === '0') {
      document.body.classList.add('is-collapsed');
    }
  } catch (error) {}
}
</script>
@php
  $admin = auth()->user();
  $adminInitial = strtoupper(mb_substr((string) ($admin->name ?? 'A'), 0, 1));
@endphp
<div class="admin-backdrop" data-sidebar-backdrop hidden></div>
<aside class="admin-sidebar" id="admin-sidebar">
  <div class="admin-sidebar-brand">
    <a href="{{ route('admin.dashboard') }}">Admin Panel</a>
    <button type="button" class="admin-icon-btn admin-collapse" data-sidebar-collapse aria-controls="admin-sidebar" aria-label="Collapse sidebar">
      <i class="bi bi-chevron-double-left"></i>
    </button>
    <button type="button" class="admin-icon-btn admin-sidebar-close" data-sidebar-close aria-controls="admin-sidebar" aria-label="Close sidebar">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  @include('admin.nav')
  <div class="admin-sidebar-foot">
    <a href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> Back to Site</a>
  </div>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <div class="admin-topbar-start">
      <button type="button" class="admin-icon-btn admin-menu-toggle" data-sidebar-open aria-controls="admin-sidebar" aria-label="Open sidebar">
        <i class="bi bi-list"></i>
      </button>
      <nav class="admin-crumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Admin</a>
      </nav>
    </div>
    <div class="admin-topbar-user">
      <a class="admin-icon-btn admin-alert" href="{{ route('admin.comments.index') }}" aria-label="Comments waiting for review">
        <i class="bi bi-bell"></i>
        @if(($pendingCommentCount ?? 0) > 0)
          <span class="admin-count">{{ $pendingCommentCount > 99 ? '99+' : $pendingCommentCount }}</span>
        @endif
      </a>
      <span class="admin-role">Admin</span>
      <div class="admin-user-meta">
        <p class="admin-user-name">{{ $admin->name ?? '—' }}</p>
        <p class="admin-user-email">{{ $admin->email ?? '—' }}</p>
      </div>
      <button type="button" class="admin-avatar" data-user-toggle aria-label="Open profile menu" aria-expanded="false" aria-controls="admin-user-menu">{{ $adminInitial }}</button>
      <div class="admin-user-menu" id="admin-user-menu" data-user-menu hidden role="menu">
        <a href="{{ route('home') }}" role="menuitem">Back to Site</a>
        <form method="post" action="{{ route('admin.logout') }}">
          @csrf
          <button type="submit" role="menuitem">Logout</button>
        </form>
      </div>
    </div>
  </header>
  <main class="admin-content">
    @hasSection('pageTitle')
      <div class="admin-page-head">
        <div>
          <h1>@yield('pageTitle')</h1>
          @hasSection('pageDescription')
            <p>@yield('pageDescription')</p>
          @endif
        </div>
        @hasSection('pageActions')
          <div class="admin-page-actions">@yield('pageActions')</div>
        @endif
      </div>
    @endif
    @if(session('status'))
      <div class="admin-flash" role="status">
        <i class="bi bi-check-circle"></i>
        {{ session('status') }}
      </div>
    @endif
    @yield('content')
  </main>
</div>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script>
(function () {
  var shell = document.body;
  var desktop = window.matchMedia('(min-width: 992px)');
  var backdrop = shell.querySelector('[data-sidebar-backdrop]');

  function setMobile(open) {
    shell.classList.toggle('is-mobile-open', open);
    if (backdrop) backdrop.hidden = !open;
  }

  shell.querySelector('[data-sidebar-collapse]')?.addEventListener('click', function () {
    shell.classList.add('is-collapsed');
    setMobile(false);
    try { localStorage.setItem('admin-sidebar-open', '0'); } catch (error) {}
  });

  shell.querySelector('[data-sidebar-open]')?.addEventListener('click', function () {
    if (desktop.matches) {
      shell.classList.remove('is-collapsed');
      try { localStorage.setItem('admin-sidebar-open', '1'); } catch (error) {}
      return;
    }
    setMobile(true);
  });

  shell.querySelector('[data-sidebar-close]')?.addEventListener('click', function () {
    setMobile(false);
  });

  backdrop?.addEventListener('click', function () {
    setMobile(false);
  });

  var toggle = shell.querySelector('[data-user-toggle]');
  var menu = shell.querySelector('[data-user-menu]');

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    setMobile(false);
    if (menu) menu.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
  });

  if (!toggle || !menu) return;

  toggle.addEventListener('click', function () {
    var open = menu.hidden;
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', function (event) {
    if (toggle.contains(event.target) || menu.contains(event.target)) return;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  });
})();
</script>
@stack('scripts')
</body>
</html>
