<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $title ?? 'Admin' }} | Royal Indian Stones</title>
<link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/admin.css') }}" rel="stylesheet">
</head>
<body>
<header class="royal-header shadow-sm">
  <nav class="navbar navbar-expand-lg navbar-dark px-3 py-2">
    <a class="d-flex align-items-center text-decoration-none" href="{{ route('admin.enquiries') }}">
      <img src="{{ asset('assets/img/royal-indian-stones-logo.png') }}" height="50" alt="Royal Indian Stones" class="me-2 bg-white rounded px-1">
      <h1 class="brand-title">Royal Indian Stones</h1>
    </a>
    <button class="navbar-toggler ms-auto border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Open menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end d-none d-lg-flex">
      @include('admin.nav')
    </div>
  </nav>
  <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="adminMenu" aria-labelledby="adminMenuLabel">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title text-warning" id="adminMenuLabel">Menu</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3 py-4">
      @include('admin.nav')
    </div>
  </div>
</header>
<main class="container py-4">
  @if(session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('status') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @yield('content')
</main>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
