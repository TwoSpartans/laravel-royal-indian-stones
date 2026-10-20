@extends('layouts.site')

@section('content')
<main id="main" class="catalogue-page">
  <section class="catalogue-intro">
    <div class="container">
      <p class="eyebrow">The collection</p>
      <h1>Natural stones of India</h1>
      <p>Choose a material to see the stones we supply from India.</p>
    </div>
  </section>
  <section class="home-categories">
    <div class="container">
      <div class="category-grid">
        @foreach($categories as $category)
          <a class="category-card" href="{{ url('/'.$category->slug) }}">
            @if($category->image)
              <x-picture src="{{ $category->image }}" alt="{{ $category->name }} stone collection" loading="lazy" sizes="(max-width: 767px) calc(50vw - 24px), (max-width: 991px) calc(33vw - 16px), 340px" />
            @endif
            <div><h3>{{ $category->name }}</h3><span aria-hidden="true">↗</span></div>
          </a>
        @endforeach
      </div>
    </div>
  </section>
</main>
@endsection
