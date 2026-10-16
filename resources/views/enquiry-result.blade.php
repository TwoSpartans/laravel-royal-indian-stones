<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $ok ? 'Thank you' : 'Please check your request' }} | Royal Indian Stones</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/improvements.css') }}?v=26">
</head>
<body>
<main class="form-response">
  <h1>{{ $ok ? 'Thank you' : 'Please check your request' }}</h1>
  <p>{{ $message }}</p>
  <p><a href="{{ route('contact') }}">Return to contact</a> · <a href="{{ url('/products') }}">Explore our stones</a></p>
</main>
</body>
</html>
