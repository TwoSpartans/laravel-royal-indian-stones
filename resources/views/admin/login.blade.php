<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Royal Indian Stones | Login</title>
<link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/admin.css') }}" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<header class="container text-center my-4">
  <img src="{{ asset('assets/img/royal-indian-stones-logo.png') }}" height="80" class="mb-2" alt="Royal Indian Stones">
  <h1 class="h3 text-uppercase"><u>Royal Indian Stones</u></h1>
</header>
<main class="container flex-grow-1 d-flex justify-content-center align-items-center">
  <div class="col-12 col-sm-8 col-md-6 col-lg-5 login-box shadow-sm">
    <h2 class="text-center mb-4">Login</h2>
    <form method="post" action="{{ route('admin.login.store') }}">
      @csrf
      <div class="mb-3">
        <label for="email" class="form-label fw-semibold">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control text-center" placeholder="Enter email" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label fw-semibold">Password</label>
        <input id="password" name="password" type="password" class="form-control text-center" placeholder="Enter password" required>
      </div>
      <div class="form-check mb-4">
        <input type="checkbox" class="form-check-input" id="showPassword" onclick="document.getElementById('password').type = this.checked ? 'text' : 'password'">
        <label class="form-check-label" for="showPassword">Show Password</label>
      </div>
      @error('email')<p class="text-danger">{{ $message }}</p>@enderror
      <div class="d-grid">
        <button type="submit" class="btn btn-dark text-warning fw-bold"><i class="bi bi-box-arrow-in-right"></i> Log In</button>
      </div>
    </form>
  </div>
</main>
<footer class="text-center py-3 mt-5"><hr><div>Royal Indian Stones</div></footer>
</body>
</html>
