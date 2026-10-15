@extends('layouts.site')

@section('content')
<main id="main">
  <section class="catalogue-intro">
    <div class="container">
      <h1>Sitemap</h1>
      <h2>Pages</h2>
      <ul>
        <li><a href="{{ url('/') }}">Home</a></li>
        <li><a href="{{ url('/products') }}">Find your stone</a></li>
        <li><a href="{{ url('/natural-stones-of-india') }}">Stone categories</a></li>
        <li><a href="{{ route('contact') }}">Request a quote</a></li>
        <li><a href="{{ url('/aboutus') }}">About us</a></li>
        <li><a href="{{ url('/project-gallery') }}">Project gallery</a></li>
        <li><a href="{{ url('/faq') }}">FAQ</a></li>
        <li><a href="{{ url('/blog') }}">Journal</a></li>
        @foreach($pages as $page)
          @if($page->slug !== 'aboutus')
            <li><a href="{{ url('/'.$page->slug) }}">{{ $page->title }}</a></li>
          @endif
        @endforeach
      </ul>
      <h2>Journal</h2>
      <ul>
        @foreach($posts as $post)
          <li><a href="{{ url('/'.$post->slug) }}">{{ $post->title }}</a></li>
        @endforeach
      </ul>
      <h2>Categories</h2>
      <ul>
        @foreach($categories as $category)
          <li><a href="{{ url('/'.$category->slug) }}">{{ $category->name }}</a></li>
        @endforeach
      </ul>
      <h2>Products</h2>
      <ul>
        @foreach($products as $product)
          <li><a href="{{ url('/'.$product->slug) }}">{{ $product->name }}</a></li>
        @endforeach
      </ul>
    </div>
  </section>
</main>
@endsection
