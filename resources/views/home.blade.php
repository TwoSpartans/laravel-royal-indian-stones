@extends('layouts.site')

@section('content')
@if($heroAvif)
  @push('head')
    <link rel="preload" as="image" type="image/avif" imagesrcset="{{ $heroAvif }}" imagesizes="100vw" fetchpriority="high">
  @endpush
@endif
<main id="main" class="home-page">
  <section id="hero" class="home-hero">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-touch="true" data-bs-interval="false" aria-roledescription="carousel" aria-label="Featured natural stones">
      <div class="carousel-inner">
        @foreach($slides as $index => $slide)
          <div @class(['carousel-item', 'active' => $index === 0])>
            <x-picture class="hero-image" src="{{ $slide['image'] }}" :alt="$slide['alt']" width="1600" height="900" sizes="100vw" :loading="$index === 0 ? 'eager' : 'lazy'" />
            <div class="hero-shade"></div>
            <div class="container hero-copy">
              <p class="eyebrow">{{ $slide['eyebrow'] }}</p>
              @if($index === 0)
                <h1 id="hero-title">{{ $slide['title'] }}@if($slide['subtitle'] !== '')<br>{{ $slide['subtitle'] }}@endif</h1>
              @else
                <h2>{{ $slide['title'] }}</h2>
              @endif
              <p>{{ $slide['text'] }}</p>
              <div class="button-row">
                <a class="ris-button" href="{{ url($slide['url']) }}">{{ $slide['label'] }} <span aria-hidden="true">↗</span></a>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon bx bx-left-arrow" aria-hidden="true"></span>
        <span class="visually-hidden">Previous slide</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon bx bx-right-arrow" aria-hidden="true"></span>
        <span class="visually-hidden">Next slide</span>
      </button>
      <div class="carousel-indicators" id="hero-carousel-indicators"></div>
    </div>
  </section>
  <section class="supplier-band" aria-labelledby="supplier-title">
    <div class="container supplier-band-inner">
      <div>
        <h2 id="supplier-title">{{ $supplier['heading'] }}</h2>
        <p>{{ $supplier['body'] }}</p>
      </div>
    </div>
  </section>
  <section class="home-categories stone-origin" aria-labelledby="categories-title">
    <div class="container">
      <div class="stone-origin-copy">
        <div>
          <p class="eyebrow" aria-hidden="true">&nbsp;</p>
          <h2 id="categories-title">{{ $exploreCopy['heading'] }}</h2>
        </div>
        <p>{{ $exploreCopy['body'] }}</p>
      </div>
      <div class="category-grid">
        @foreach($explore as $card)
          @if($card['image'] !== '')
            <a class="catalogue-card" href="{{ url($card['url']) }}">
              <x-picture src="{{ $card['image'] }}" :alt="$card['alt']" width="800" height="600" loading="lazy" sizes="(max-width: 575px) calc(100vw - 32px), (max-width: 991px) calc(50vw - 20px), 380px" />
              <div class="catalogue-card-body">
                <div class="catalogue-card-title">
                  <h3>{{ $card['name'] }}</h3>
                  <span class="text-link">View collection →</span>
                </div>
              </div>
            </a>
          @endif
        @endforeach
      </div>
    </div>
  </section>
  <section class="home-stones stone-origin" aria-labelledby="origin-title">
    <div class="container">
      <div class="stone-origin-copy">
        <div>
          <p class="eyebrow" aria-hidden="true">&nbsp;</p>
          <h2 id="origin-title">{{ $stonesCopy['heading'] }}</h2>
        </div>
        <p>{{ $stonesCopy['body'] }}</p>
      </div>
      <div class="category-grid">
        @foreach($stones as $stone)
          @if($stone['image'] !== '')
            <a class="catalogue-card" href="{{ url($stone['url']) }}">
              <x-picture src="{{ $stone['image'] }}" :alt="$stone['alt']" width="800" height="600" loading="lazy" sizes="(max-width: 575px) calc(100vw - 32px), (max-width: 991px) calc(50vw - 20px), 340px" />
              <div class="catalogue-card-body">
                <div class="catalogue-card-title">
                  <h3>{{ $stone['name'] }}</h3>
                  <span class="text-link">View stone →</span>
                </div>
              </div>
            </a>
          @endif
        @endforeach
      </div>
    </div>
  </section>
  @if($posts->isNotEmpty())
    <section class="home-journal" aria-labelledby="journal-title">
      <div class="container">
        <div class="section-heading">
          <div><p class="eyebrow" aria-hidden="true">&nbsp;</p><h2 id="journal-title">{{ $journalCopy['heading'] }}</h2></div>
          <a class="text-link" href="{{ url('/blog') }}">{{ $journalCopy['button_label'] }}</a>
        </div>
        <div class="journal-grid">
          @foreach($posts as $post)
            <a class="catalogue-card" href="{{ url('/'.$post->slug) }}">
              @if($post->image)
                <x-picture src="{{ $post->image }}" :alt="$post->featured_image_alt ?: $post->title" width="800" height="600" loading="lazy" sizes="(max-width: 767px) calc(100vw - 40px), 33vw" />
              @endif
              <div class="catalogue-card-body">
                @if($post->postCategory)
                  <p class="eyebrow">{{ $post->postCategory->name }}</p>
                @endif
                <div class="catalogue-card-title">
                  <h3>{{ $post->title }}</h3>
                  <span class="text-link">Read note →</span>
                </div>
                @if($post->published_on)
                  <p class="catalogue-card-detail"><time datetime="{{ $post->published_on->toDateString() }}">{{ $post->published_on->format('j F Y') }}</time></p>
                @endif
              </div>
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif
</main>
@endsection
