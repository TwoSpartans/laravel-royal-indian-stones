@extends('layouts.site')

@section('content')
<main id="main" class="catalogue-page">
  <section class="catalogue-intro">
    <div class="container">
      <p class="eyebrow">Royal Indian Stones</p>
      <h1>{{ $category->name }}</h1>
      @if($category->introduction)
        <p>{{ $category->introduction }}</p>
      @elseif($category->description)
        <p>{{ $category->description }}</p>
      @endif
      <p><a class="text-link" href="{{ url('/products') }}?material={{ urlencode($category->name) }}">Filter this material in search →</a></p>
    </div>
  </section>
  <section class="catalogue-section">
    <div class="container">
      <p id="product_count">{{ $products->count() }} {{ $products->count() === 1 ? 'product' : 'products' }}</p>
      <div class="product-grid">
        @forelse($products as $product)
          <a class="catalogue-card" href="{{ url('/'.$product->slug) }}" data-track="product_view" data-product="{{ $product->slug }}">
            @if($image = $product->images->first())
              <img src="{{ asset($image->path) }}" alt="{{ $product->name }}" loading="lazy" decoding="async" @if($image->width) width="{{ $image->width }}" @endif @if($image->height) height="{{ $image->height }}" @endif>
            @endif
            <div class="catalogue-card-body">
              <p class="eyebrow">{{ $category->name }}</p>
              <h2>{{ $product->name }}</h2>
              <p class="catalogue-card-detail">{{ collect([$product->color])->merge(array_slice($product->finishes ?? [], 0, 2))->filter()->implode(' · ') }}</p>
              <span class="text-link">View stone →</span>
            </div>
          </a>
        @empty
          <div class="empty-state"><h2>No products in this category yet</h2><p><a href="{{ route('contact') }}">Ask our team</a> about {{ $category->name }}.</p></div>
        @endforelse
      </div>
    </div>
  </section>
</main>
@endsection
