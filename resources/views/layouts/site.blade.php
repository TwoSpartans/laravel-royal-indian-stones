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
<meta property="og:title" content="{{ $title ?? 'Royal Indian Stones' }}">
@if(!empty($description))
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:image" content="{{ $shareImage ?? asset('assets/img/slide/slide-1.jpg') }}">
<meta property="og:site_name" content="Royal Indian Stones">
@php
    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Royal Indian Stones',
        'url' => url('/'),
        'email' => 'info@royalindianstones.com',
        'telephone' => '+91-876-987-3114',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Rajpurohit Bhawan, Plot-17, Golf Course Road, Air Force',
            'addressLocality' => 'Jodhpur',
            'postalCode' => '342011',
            'addressRegion' => 'Rajasthan',
            'addressCountry' => 'IN',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<link href="{{ asset('assets/img/favicon.png') }}" rel="icon">
<link href="{{ asset('assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/vendor/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/style.css') }}?v=2" rel="stylesheet">
<link href="{{ asset('assets/css/improvements.css') }}?v=67" rel="stylesheet">
@php
    $deferredStyles = [
        'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Open+Sans:wght@400;600&family=Poppins:wght@300;400&family=Source+Sans+3:wght@400;500;600&display=swap',
        asset('assets/vendor/animate.css/animate.min.css'),
        asset('assets/vendor/aos/aos.css'),
        asset('assets/vendor/glightbox/css/glightbox.min.css'),
        asset('assets/vendor/swiper/swiper-bundle.min.css'),
    ];
@endphp
@foreach ($deferredStyles as $stylesheet)
<link rel="preload" href="{{ $stylesheet }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="{{ $stylesheet }}" rel="stylesheet"></noscript>
@endforeach
@stack('head')
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PX68BW5FMP"></script>
<script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config','G-PX68BW5FMP');</script>
</head>
<body @if(!empty($bodyClass)) class="{{ $bodyClass }}" @endif @isset($productId) data-product-id="{{ $productId }}" @endisset>
@include('partials.header')
@yield('content')
@include('partials.footer')
<a href="#main" class="back-to-top" aria-label="Back to top"><span class="back-to-top-arrow"><i class="bi bi-arrow-up" aria-hidden="true"></i></span></a>
<script src="{{ asset('assets/vendor/aos/aos.js') }}" defer></script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/glightbox/js/glightbox.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/isotope-layout/isotope.pkgd.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/swiper/swiper-bundle.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/waypoints/noframework.waypoints.js') }}" defer></script>
<script src="{{ asset('assets/js/ajax_jquery.js') }}" defer></script>
<script src="{{ asset('assets/js/main.js') }}?v=9" defer></script>
<script src="{{ asset('assets/js/site.js') }}?v=3" defer></script>
@stack('scripts')
</body>
</html>
