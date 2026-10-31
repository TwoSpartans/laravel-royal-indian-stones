@extends('layouts.site')

@section('content')
<main id="main">
  <section class="e-catalogue">
    <div class="container">
      <p class="eyebrow">Royal Indian Stones</p>
      <h1>E-catalogue</h1>
      <p class="e-catalogue-intro">Browse the natural stones we cut and export from India. Open a collection to see the products in that material.</p>
      @if($collections === [])
        <p class="empty-state">The catalogue is being updated. <a href="{{ route('contact') }}">Contact us</a> and we will send the current range.</p>
      @else
        <div class="category-grid">
          @foreach($collections as $collection)
            <a class="catalogue-card" href="{{ url($collection['url']) }}">
              @if($collection['image'] !== '')
                <x-picture src="{{ $collection['image'] }}" :alt="$collection['alt']" width="800" height="600" loading="lazy" sizes="(max-width: 575px) calc(100vw - 32px), (max-width: 991px) calc(50vw - 20px), 380px" />
              @else
                <span class="e-catalogue-placeholder" aria-hidden="true"></span>
              @endif
              <div class="catalogue-card-body">
                <div class="catalogue-card-title">
                  <h2>{{ $collection['name'] }}</h2>
                </div>
                <p class="catalogue-card-detail">{{ $collection['summary'] }}</p>
                @if($collection['products'] > 0)
                  <p class="e-catalogue-count">{{ $collection['products'] }} {{ $collection['products'] === 1 ? 'product' : 'products' }}</p>
                @endif
                <span class="text-link">View collection →</span>
              </div>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </section>
</main>
@endsection
