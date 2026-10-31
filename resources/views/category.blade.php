@extends('layouts.site')

@section('content')
<main id="main" class="catalogue-page">
  <section class="catalogue-intro">
    <div class="container">
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
      @if($listing['options'] !== [])
        <nav class="category-filter" aria-label="{{ $listing['label'] }}">
          @foreach($listing['options'] as $option)
            <a href="{{ $option['url'] }}" @class(['is-active' => $option['active']]) @if($option['active']) aria-current="page" @endif>{{ $option['label'] }}</a>
          @endforeach
        </nav>
      @endif
      <p id="product_count">{{ $listing['count'] }} {{ $listing['count'] === 1 ? 'product' : 'products' }}</p>
      @if($listing['count'] === 0)
        <div class="empty-state"><h2>No products in this category yet</h2><p><a href="{{ route('contact') }}">Ask our team</a> about {{ $category->name }}.</p></div>
      @else
        @foreach($listing['groups'] as $group)
          @if(!$loop->first)
            <hr class="category-group-rule">
          @endif
          <div class="category-group">
            @if($group['showHeading'])
              <h2 class="category-group-title">{{ $group['label'] }}</h2>
            @endif
            <div class="product-grid">
              @foreach($group['products'] as $product)
                <a class="catalogue-card" href="{{ url('/'.$product->slug) }}" data-track="product_view" data-product="{{ $product->slug }}">
                  @if($image = $product->images->first())
                    <x-picture src="{{ $image->path }}" alt="{{ $product->name }}" :width="$image->width ? (string) $image->width : null" :height="$image->height ? (string) $image->height : null" loading="lazy" sizes="(max-width: 450px) calc(100vw - 32px), (max-width: 991px) calc(50vw - 20px), 32vw" />
                  @endif
                  <div class="catalogue-card-body">
                    <p class="eyebrow">{{ $category->name }}</p>
                    <div class="catalogue-card-title">
                      <h2>{{ $product->name }}</h2>
                      <span class="text-link">View stone →</span>
                    </div>
                    <p class="catalogue-card-detail">{{ collect([$product->color])->merge(array_slice($product->finishes ?? [], 0, 2))->filter()->implode(' · ') }}</p>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </section>
</main>
@endsection
