@extends('layouts.site')

@section('content')
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>{{ $product->name }}</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          @if($product->category)
            <li><a href="{{ url('/'.$product->category->slug) }}">{{ $product->category->name }}</a></li>
          @endif
          <li>{{ $product->name }}</li>
        </ol>
      </div>
    </div>
  </section>
  <section id="portfolio-details" class="portfolio-details">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-8">
          @if($product->images->isNotEmpty())
            <div class="portfolio-details-slider swiper">
              <div class="swiper-wrapper align-items-center">
                @foreach($product->images as $image)
                  <div class="swiper-slide">
                    <img src="{{ asset($image->path) }}" alt="{{ $image->alt }}" @if($loop->first) loading="eager" @else loading="lazy" @endif decoding="async" @if($image->width) width="{{ $image->width }}" @endif @if($image->height) height="{{ $image->height }}" @endif @if($image->srcset) srcset="{{ $image->srcset }}" sizes="(max-width: 991px) 100vw, 66vw" @endif>
                  </div>
                @endforeach
              </div>
              <div class="swiper-pagination"></div>
            </div>
          @endif
        </div>
        <div class="col-lg-4 mt-3">
          <div class="product-info">
            <h1 class="text-center p-0">{{ $product->name }}</h1>
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <tbody>
                  @foreach($product->specs as $spec)
                    <tr>
                      <th scope="row">{{ $spec->label }}</th>
                      <td>
                        @if($spec->label === 'Packaging')
                          <a href="{{ url('/packaging-type') }}">View packaging options</a>; confirm with your quotation.
                        @else
                          {{ $spec->value }}
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <p class="product-order-note">Natural stone can vary in shade and texture. Confirm your selected finish, dimensions and order requirements with our team.</p>
          </div>
          <hr>
          <div class="row justify-content-center">
            <a class="ris-button product-quote" href="{{ route('contact', ['product' => $product->slug]) }}" data-track="quote_click" data-product="{{ $product->slug }}">Request a Quote for This Stone</a>
          </div>
        </div>
      </div>
      @if($product->detail)
        <hr>
        <div class="product-description">
          <h3>Product Detail</h3>
          @foreach($product->paragraphs($product->detail) as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
          @endforeach
        </div>
      @endif
      <hr>
      <div class="row pb-4">
        <div class="col-lg-8 col-md-6 my-1">
          @if($product->application)
            <div class="product-application my-2">
              <h3 class="text-center">Application Areas</h3>
              <hr>
              @foreach($product->paragraphs($product->application) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
              @endforeach
            </div>
          @endif
        </div>
        <div class="product_sidebar col-lg-4 my-2">
          <h4 class="product_sidebar-title text-center">Relevant Products</h4>
          <hr>
          <div class="product_sidebar_item recent-posts">
            @foreach($related as $item)
              <div class="post-item clearfix row">
                <h4 class="text-center"><a href="{{ url('/'.$item->slug) }}"><u>{{ $item->name }}</u></a></h4>
                @if($image = $item->images->first())
                  <div class="col-lg-4 col-md-6 d-flex align-items-center justify-content-center">
                    <a href="{{ url('/'.$item->slug) }}"><img src="{{ asset($image->path) }}" alt="{{ $item->name }}" width="400" height="300" loading="lazy" decoding="async"></a>
                  </div>
                @endif
                <div class="col-lg-8 col-md-6">
                  {{ $item->summary }}
                  <a href="{{ url('/'.$item->slug) }}">Read More</a>
                </div>
              </div>
              @if(!$loop->last)<hr>@endif
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
@endsection

@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
