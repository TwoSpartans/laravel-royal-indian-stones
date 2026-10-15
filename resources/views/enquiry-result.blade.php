<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $ok ? 'Thank you' : 'Please check your request' }} | Royal Indian Stones</title>
<link rel="stylesheet" href="{{ asset('assets/css/improvements.css') }}">
</head>
<body>
<main class="form-response">
  <h1>{{ $ok ? 'Thank you' : 'Please check your request' }}</h1>
  <p>{{ $message }}</p>
  <p><a href="{{ route('contact') }}">Return to contact</a> · <a href="{{ url('/products') }}">Explore our stones</a></p>
</main>
</body>
</html>
