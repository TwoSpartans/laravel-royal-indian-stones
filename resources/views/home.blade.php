@extends('layouts.site')

@section('content')
<main id="main" class="home-page">
  <section id="hero" class="home-hero" aria-labelledby="hero-title">
    <img class="hero-image" src="{{ asset('assets/img/slide/slide-1.jpg') }}" alt="Natural sandstone used in an architectural setting" fetchpriority="high" loading="eager">
    <div class="hero-shade"></div>
    <div class="container hero-copy">
      <p class="eyebrow">Royal Indian Stones · Jodhpur, India</p>
      <h1 id="hero-title">Indian natural stone.<br>For the spaces you imagine.</h1>
      <p>Discover sandstone, granite, marble and more for architecture, interiors and outdoor projects. Tell us what you need, and let’s find your stone.</p>
      <div class="button-row">
        <a class="ris-button" href="{{ url('/products') }}" data-track="explore_products">Explore Our Stones <span aria-hidden="true">↗</span></a>
        <a class="ris-button ris-button-light" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a>
      </div>
      <div class="hero-notes"><span>Tiles &amp; slabs</span><span>Landscape stone</span><span>Custom stone articles</span></div>
    </div>
  </section>
  <section class="home-categories" aria-labelledby="categories-title">
    <div class="container">
      <div class="section-heading">
        <div><p class="eyebrow">A material for every project</p><h2 id="categories-title">Explore the collection</h2></div>
        <a class="text-link" href="{{ url('/products') }}">Search all products <span aria-hidden="true">→</span></a>
      </div>
      <div class="category-grid">
        @foreach($categories as $category)
          @if($category->slug !== 'stone-applications')
            <a class="category-card" href="{{ url('/'.$category->slug) }}">
              @if($category->image)
                <img src="{{ asset($category->image) }}" alt="{{ $category->name }} stone collection">
              @endif
              <div><h3>{{ $category->name }}</h3><span aria-hidden="true">↗</span></div>
            </a>
          @endif
        @endforeach
      </div>
    </div>
  </section>
  <section class="home-featured soft-section" aria-labelledby="featured-title">
    <div class="container">
      <div class="section-heading">
        <div><p class="eyebrow">A closer look</p><h2 id="featured-title">Discover your next material</h2></div>
        <p>Explore colour, texture and finish before choosing the stone for your project.</p>
      </div>
      <div class="featured-grid">
        @foreach($featured as $product)
          <a class="featured-card" href="{{ url('/'.$product->slug) }}" data-track="product_view" data-product="{{ $product->slug }}">
            @if($image = $product->catalogueImage())
              <img src="{{ asset($image->path) }}" alt="{{ $product->name }}">
            @endif
            <div>
              <p class="eyebrow">{{ $product->category?->name }}</p>
              <h3>{{ $product->name }}</h3>
              <span class="text-link">View specifications →</span>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </section>
  <section class="home-process" aria-labelledby="process-title">
    <div class="container">
      <p class="eyebrow">From selection to shipment</p>
      <h2 id="process-title">Stone supply, with attention to detail</h2>
      <div class="process-grid">
        <a href="{{ url('/quality-control') }}" class="process-card" data-track="trust_click"><span class="step-number">01</span><h3>Quality checks</h3><p>See how stone dimensions, finishes and your specifications are checked during production.</p><span class="text-link">Our quality process →</span></a>
        <a href="{{ url('/packaging-type') }}" class="process-card" data-track="trust_click"><span class="step-number">02</span><h3>Packaging options</h3><p>Explore packaging for tiles, slabs, blocks, cobbles and pebbles.</p><span class="text-link">View packaging →</span></a>
        <a href="{{ route('contact') }}" class="process-card" data-track="quote_click"><span class="step-number">03</span><h3>Your requirements</h3><p>Share your sizes, finish, quantity and destination so we can discuss a suitable quotation.</p><span class="text-link">Start an enquiry →</span></a>
      </div>
    </div>
  </section>
  <section class="home-projects soft-section" aria-labelledby="projects-title">
    <div class="container">
      <div class="section-heading">
        <div><p class="eyebrow">Stone in context</p><h2 id="projects-title">Ideas from our project gallery</h2></div>
        <a class="text-link" href="{{ url('/project-gallery') }}" data-track="trust_click">View the gallery →</a>
      </div>
      <div class="project-grid">
        <a href="{{ url('/project-gallery') }}#project-2" class="project-card"><img src="{{ asset('assets/img/projects/dholpur-beige-sandstone-wall-cladding-building-project.jpg') }}" alt="Beige sandstone on a building facade"><div><h3>Sandstone wall cladding</h3><p>Natural texture for architectural surfaces.</p></div></a>
        <a href="{{ url('/project-gallery') }}#project-1" class="project-card"><img src="{{ asset('assets/img/projects/black-granite-wall-cladding-slabs-design-cnc-work.jpg') }}" alt="Carved black granite wall cladding"><div><h3>Granite detailing</h3><p>Explore decorative stonework and finishes.</p></div></a>
      </div>
    </div>
  </section>
  <section class="enquiry-band">
    <div class="container">
      <div><p class="eyebrow">Let’s discuss your project</p><h2>Found a stone you like?</h2><p>Send your requirements for a product-specific quotation.</p></div>
      <a class="ris-button" href="{{ route('contact') }}" data-track="quote_click">Request a Quote →</a>
    </div>
  </section>
</main>
@endsection
