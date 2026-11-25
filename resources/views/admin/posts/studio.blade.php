<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Blog Studio — {{ $post->title }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/js/blog-studio/main.tsx'])
</head>
<body style="margin:0;height:100dvh;background:#F7F5F0;color:#202820;">
    <script type="application/json" id="blog-studio-bootstrap">{!! json_encode($bootstrap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <div id="blog-studio-root" style="height:100dvh;"></div>
</body>
</html>
