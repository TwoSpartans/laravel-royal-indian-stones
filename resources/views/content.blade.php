@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>{{ $page->title }}</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>{{ $page->title }}</li>
        </ol>
      </div>
    </div>
  </section>
  <div class="page-body">
    {!! app(\App\Support\ResponsivePicture::class)->rewriteHtml(preg_replace(['/\sstyle="[^"]*(?:height\s*:\s*100%|webkit-fill-available)[^"]*"/i', '/\ssizes="[^"]*"/i'], '', $page->body) ?? '') !!}
  </div>
</main>
@endsection
