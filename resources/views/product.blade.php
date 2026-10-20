@extends('layouts.site')

@php
    $summary = trim((string) $product->summary);
    $detailParagraphs = array_map(
        fn (string $paragraph): string => trim(preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph),
        $product->paragraphs($product->detail),
    );
    $lead = $summary;
    if ($detailParagraphs !== []) {
        preg_match_all('/.+?[.!?]+(?=\s|$)/u', $detailParagraphs[0], $sentences);
        $opening = trim(implode(' ', array_slice($sentences[0] ?? [], 0, 2)));
        if ($opening !== '') {
            $lead = $opening;
            $remainder = trim(mb_substr($detailParagraphs[0], mb_strlen($opening)));
            if ($remainder === '') {
                array_shift($detailParagraphs);
            } else {
                $detailParagraphs[0] = $remainder;
            }
        }
    }
    $applicationParagraphs = array_map(
        fn (string $paragraph): string => trim(preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph),
        $product->paragraphs($product->application),
    );
    $imageCount = $product->images->count();
@endphp

@section('content')
<main id="main" class="product-page">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <nav aria-label="Breadcrumb">
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          @if($product->category)
            <li><a href="{{ url('/'.$product->category->slug) }}">{{ $product->category->name }}</a></li>
          @endif
          <li aria-current="page">{{ $product->name }}</li>
        </ol>
      </nav>
    </div>
  </section>

  <section class="product-stage" aria-labelledby="product-title">
    <div class="container product-stage-grid">
      <div class="product-gallery">
        @if($imageCount > 0)
          <div class="product-frame">
            @if($imageCount > 1)
              <div class="portfolio-details-slider product-slider swiper">
                <div class="swiper-wrapper">
                  @foreach($product->images as $image)
                    <div class="swiper-slide">
                      <x-picture src="{{ $image->path }}" alt="{{ $image->alt ?: $product->name }}" :width="$image->width ? (string) $image->width : null" :height="$image->height ? (string) $image->height : null" :loading="$loop->first ? 'eager' : 'lazy'" sizes="(max-width: 991px) 100vw, 58vw" />
                    </div>
                  @endforeach
                </div>
              </div>
            @else
              @php $image = $product->images->first(); @endphp
              <x-picture src="{{ $image->path }}" alt="{{ $image->alt ?: $product->name }}" :width="$image->width ? (string) $image->width : null" :height="$image->height ? (string) $image->height : null" loading="eager" sizes="(max-width: 991px) 100vw, 58vw" />
            @endif
          </div>
          @if($imageCount > 1)
            <div class="product-thumbs">
              @foreach($product->images as $image)
                <button type="button" class="product-thumb @if($loop->first) is-active @endif" data-slide="{{ $loop->index }}" aria-label="Show photograph {{ $loop->iteration }} of {{ $imageCount }}" @if($loop->first) aria-pressed="true" @else aria-pressed="false" @endif>
                  <x-picture src="{{ $image->path }}" alt="" width="84" height="84" loading="lazy" sizes="84px" />
                </button>
              @endforeach
            </div>
          @endif
        @else
          <div class="product-frame product-frame-empty">
            <p>Photograph coming soon</p>
          </div>
        @endif
      </div>

      <div class="product-panel">
        @if($product->category)
          <p class="eyebrow"><a href="{{ url('/'.$product->category->slug) }}">{{ $product->category->name }}</a></p>
        @endif
        <h1 id="product-title">{{ $product->name }}</h1>
        @if($lead !== '')
          <p class="product-lead">{{ $lead }}</p>
        @endif
        <a class="ris-button product-quote" href="{{ route('contact', ['product' => $product->slug]) }}" data-track="quote_click" data-product="{{ $product->slug }}">Request a Quote for This Stone <span aria-hidden="true">→</span></a>
        <p class="product-order-note">Natural stone varies in shade and texture. Confirm the finish, dimensions and quantity with our team.</p>
        @if($product->specs->isNotEmpty())
          <p class="eyebrow product-specs-label">Specifications</p>
          <dl class="product-specs">
            @foreach($product->specs as $spec)
              <div>
                <dt>{{ $spec->label }}</dt>
                <dd>
                  @if($spec->label === 'Packaging')
                    <a href="{{ url('/packaging-type') }}">View packaging options</a>; confirm with your quotation.
                  @else
                    {{ $spec->value }}
                  @endif
                </dd>
              </div>
            @endforeach
          </dl>
        @endif
      </div>
    </div>
  </section>

  @if($detailParagraphs !== [] || $applicationParagraphs !== [])
    <section class="product-copy">
      <div class="container product-copy-grid @if($detailParagraphs === [] || $applicationParagraphs === []) is-single @endif">
        @if($detailParagraphs !== [])
          <article class="product-description">
            <h2>About this stone</h2>
            @foreach($detailParagraphs as $paragraph)
              <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
          </article>
        @endif
        @if($applicationParagraphs !== [])
          <article class="product-application">
            <h2>Where it is used</h2>
            @foreach($applicationParagraphs as $paragraph)
              <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
          </article>
        @endif
      </div>
    </section>
  @endif

  @if($related->isNotEmpty())
    <section class="product-related-section" aria-labelledby="related-title">
      <div class="container">
        <div class="section-heading">
          <div>
            <p class="eyebrow">{{ $product->category?->name ?? 'The collection' }}</p>
            <h2 id="related-title">Other stones to consider</h2>
          </div>
          <div class="related-controls">
            <div class="related-nav">
              <button type="button" class="related-prev" aria-label="Previous stones"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <button type="button" class="related-next" aria-label="Next stones"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            @if($product->category)
              <a class="text-link" href="{{ url('/'.$product->category->slug) }}">View all {{ $product->category->name }}</a>
            @endif
          </div>
        </div>
        <div class="related-slider swiper" aria-label="Other stones to consider">
          <div class="swiper-wrapper">
            @foreach($related as $item)
              @php
                $relatedImage = $item->images->first();
                $relatedDetail = collect([$item->color, ...($item->finishes ?? [])])->filter()->take(3)->implode(' · ');
              @endphp
              <div class="swiper-slide">
                <a class="catalogue-card" href="{{ url('/'.$item->slug) }}" data-track="product_view" data-product="{{ $item->slug }}">
                  @if($relatedImage)
                    <x-picture src="{{ $relatedImage->path }}" alt="{{ $item->name }}" width="{{ $relatedImage->width ?: 600 }}" height="{{ $relatedImage->height ?: 450 }}" loading="lazy" sizes="(max-width: 767px) 86vw, 33vw" />
                  @endif
                  <div class="catalogue-card-body">
                    @if($product->category)
                      <p class="eyebrow">{{ $product->category->name }}</p>
                    @endif
                    <div class="catalogue-card-title">
                      <h2>{{ $item->name }}</h2>
                      <span class="text-link">View stone →</span>
                    </div>
                    @if($relatedDetail !== '')
                      <p class="catalogue-card-detail">{{ $relatedDetail }}</p>
                    @endif
                  </div>
                </a>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>
  @endif
</main>
@endsection

@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@if($related->isNotEmpty())
  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const root = document.querySelector('.related-slider');
      if (!root || typeof Swiper === 'undefined') {
        return;
      }
      new Swiper(root, {
        speed: 450,
        spaceBetween: 16,
        slidesPerView: 1.15,
        watchOverflow: true,
        navigation: {
          prevEl: '.related-prev',
          nextEl: '.related-next'
        },
        breakpoints: {
          768: { slidesPerView: 2, spaceBetween: 20 },
          992: { slidesPerView: 3, spaceBetween: 24 }
        }
      });
    });
  </script>
  @endpush
@endif

@if($imageCount > 1)
  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const slider = document.querySelector('.product-slider');
      if (!slider || !slider.swiper) {
        return;
      }
      const swiper = slider.swiper;
      const thumbs = Array.from(document.querySelectorAll('.product-thumb'));
      const mark = function () {
        thumbs.forEach(function (thumb, index) {
          const active = index === swiper.realIndex;
          thumb.classList.toggle('is-active', active);
          thumb.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
      };
      thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
          const index = Number(thumb.dataset.slide);
          if (typeof swiper.slideToLoop === 'function' && swiper.params.loop) {
            swiper.slideToLoop(index);
          } else {
            swiper.slideTo(index);
          }
        });
      });
      swiper.on('slideChange', mark);
      mark();
    });
  </script>
  @endpush
@endif
