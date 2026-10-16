<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'Royal Indian Stones' }}</title>
@if(!empty($description))
<meta name="description" content="{{ $description }}">
@endif
<link rel="canonical" href="{{ url()->current() }}">
<link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
<link href="{{ asset('assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('assets/vendor/animate.css/animate.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/aos/aos.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/style.css') }}?v=2" rel="stylesheet">
<link href="{{ asset('assets/css/improvements.css') }}?v=20" rel="stylesheet">
@stack('head')
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PX68BW5FMP"></script>
<script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config','G-PX68BW5FMP');</script>
</head>
<body @if(!empty($bodyClass)) class="{{ $bodyClass }}" @endif @isset($productId) data-product-id="{{ $productId }}" @endisset>
@include('partials.header')
@yield('content')
@include('partials.footer')
<a href="#main" class="back-to-top" aria-label="Back to top"><i class="bi bi-arrow-up" aria-hidden="true"></i></a>
<script src="{{ asset('assets/vendor/aos/aos.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
<script src="{{ asset('assets/vendor/isotope-layout/isotope.pkgd.min.js') }}"></script>
<script src="{{ asset('assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/waypoints/noframework.waypoints.js') }}"></script>
<script src="{{ asset('assets/js/ajax_jquery.js') }}"></script>
<script src="{{ asset('assets/js/main.js') }}?v=5"></script>
<script src="{{ asset('assets/js/site.js') }}?v=1" defer></script>
@stack('scripts')
</body>
</html>
