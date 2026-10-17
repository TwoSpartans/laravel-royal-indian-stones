@extends('layouts.site')

@section('content')
<main id="main" class="home-page">
  <section id="hero" class="home-hero">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-touch="true" data-bs-interval="false" aria-roledescription="carousel" aria-label="Featured natural stones">
      <div class="carousel-inner">
        @foreach([
          ['image' => 'assets/img/slide/slide-1.jpg', 'alt' => 'Umaid Bhawan Palace in Jodhpur, built in golden sandstone', 'eyebrow' => 'Sandstone · Jodhpur, India', 'title' => 'Indian natural stone.', 'text' => 'This palace is built from Jodhpur sandstone. We supply the same warm stone in tiles, slabs and custom articles, matched for colour and finish.', 'url' => '/sandstone', 'label' => 'Explore sandstone'],
          ['image' => 'assets/blog/240127/jaisalmer-yellow-wall-clading-natural-golden-limestone-beauty.jpg', 'alt' => 'Golden Jaisalmer limestone cladding on a wall', 'eyebrow' => 'Limestone · flooring and steps', 'title' => 'Quiet stone for floors and steps.', 'text' => 'Tandur and Kota limestone take a honed or leather finish and stay even in colour across large floors, courtyards and staircases.', 'url' => '/limestone', 'label' => 'Explore limestone'],
          ['image' => 'assets/img/slide/slide-3.jpg', 'alt' => 'The Taj Mahal, built in white marble', 'eyebrow' => 'Marble · monuments and interiors', 'title' => 'Marble with a long memory.', 'text' => 'White and coloured Indian marbles are cut for flooring, wall cladding, vanities and feature work, with the veining selected before it is packed.', 'url' => '/marble', 'label' => 'Explore marble'],
          ['image' => 'assets/img/projects/black-granite-wall-cladding-slabs-design-cnc-work.jpg', 'alt' => 'Carved black granite wall cladding', 'eyebrow' => 'Granite · worktops and facades', 'title' => 'Granite that stays dense and dark.', 'text' => 'Absolute Black and other Indian granites are polished or flamed for kitchen tops, cladding and exterior paving that has to take wear.', 'url' => '/granite', 'label' => 'Explore granite'],
          ['image' => 'assets/img/slide/slide-5.jpg', 'alt' => 'Stone-clad house with a slate exterior', 'eyebrow' => 'Slate · roofs and walls', 'title' => 'Slate for roofs, walls and floors.', 'text' => 'Natural cleft slate splits into thin, textured pieces for roofing, wall cladding and floors, with the surface left as the quarry split it.', 'url' => '/slate', 'label' => 'Explore slate'],
          ['image' => 'assets/img/slide/slide-6.jpg', 'alt' => 'White pebble garden with stone stepping paths', 'eyebrow' => 'Pebbles · gardens and water', 'title' => 'Pebbles for paths and water.', 'text' => 'River and tumbled pebbles are graded by colour and size for garden paths, courtyards, water features and decorative fills.', 'url' => '/stone-pebbles', 'label' => 'Explore pebbles'],
        ] as $index => $slide)
          <div @class(['carousel-item', 'active' => $index === 0])>
            <img class="hero-image" src="{{ asset($slide['image']) }}" alt="{{ $slide['alt'] }}" width="1600" height="900" @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
            <div class="hero-shade"></div>
            <div class="container hero-copy">
              <p class="eyebrow">{{ $slide['eyebrow'] }}</p>
              @if($index === 0)
                <h1 id="hero-title">{{ $slide['title'] }}<br>For the spaces you imagine.</h1>
              @else
                <h2>{{ $slide['title'] }}</h2>
              @endif
              <p>{{ $slide['text'] }}</p>
              <div class="button-row">
                <a class="ris-button" href="{{ url($slide['url']) }}">{{ $slide['label'] }} <span aria-hidden="true">↗</span></a>
                <a class="ris-button ris-button-light" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a>
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
        <h2 id="supplier-title">Natural Indian Stones Supplier, Manufacturer and Exporter</h2>
        <p>The Royal Indian Stones evolved into Production and Manufacturing of Blocks, Flooring Slabs, Flooring Tiles, Calibrated - Ready to Fix Tiles, Monuments, Tomb Stones, Sculptures, Artefacts, Cobbles, Cubes, Kerbs, Pebbles, Landscaping Stones, Fireplaces, Fountains, Balusters, Slate Veneers, Mosaics, Stone Articles, Paving Stones, Tumbled Pebbles Stones, Natural River Pebbles Stones and Landscaping Garden Stones.</p>
      </div>
      <a class="supplier-quote" href="{{ route('contact') }}" data-track="quote_click">Request a Quote</a>
    </div>
  </section>
  <section class="home-categories stone-origin" aria-labelledby="categories-title">
    <div class="container">
      <div class="stone-origin-copy">
        <div>
          <p class="eyebrow">A material for every project</p>
          <h2 id="categories-title">Explore the collection</h2>
        </div>
        <p>Sandstone, limestone, marble, granite, slate and the other stones we cut in India, chosen for floors, walls, roofs and gardens.</p>
      </div>
      <div class="category-grid">
        @foreach($categories as $category)
          @if(! in_array($category->slug, ['stone-applications', 'stone-articles'], true))
            <a class="catalogue-card" href="{{ url('/'.$category->slug) }}">
              @if($category->image)
                <img src="{{ asset($category->image) }}" alt="{{ $category->name }} stone collection" width="640" height="480" loading="lazy" decoding="async">
              @endif
              <div class="catalogue-card-body">
                <div class="catalogue-card-title">
                  <h3>{{ $category->name }}</h3>
                  <span class="text-link">View collection →</span>
                </div>
              </div>
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
          <a class="catalogue-card" href="{{ url('/'.$product->slug) }}" data-track="product_view" data-product="{{ $product->slug }}">
            @if($image = $product->catalogueImage())
              <img src="{{ asset($image->path) }}" alt="{{ $product->name }}" width="{{ $image->width ?: 800 }}" height="{{ $image->height ?: 600 }}" loading="lazy" decoding="async" @if($image->srcset) srcset="{{ $image->srcset }}" sizes="(max-width: 767px) 100vw, 33vw" @endif>
            @endif
            <div class="catalogue-card-body">
              <p class="eyebrow">{{ $product->category?->name }}</p>
              <div class="catalogue-card-title">
                <h3>{{ $product->name }}</h3>
                <span class="text-link">View stone →</span>
              </div>
              @php $featuredDetail = collect([$product->color, ...($product->finishes ?? [])])->filter()->take(3)->implode(' · '); @endphp
              @if($featuredDetail !== '')
                <p class="catalogue-card-detail">{{ $featuredDetail }}</p>
              @endif
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
  <section class="stone-origin" aria-labelledby="origin-title">
    <div class="container">
      <div class="stone-origin-copy">
        <div>
          <p class="eyebrow">Stone for every project</p>
          <h2 id="origin-title">Natural Indian Stones</h2>
        </div>
        <p>Shaped over millions of years, natural Indian stone brings distinct textures to walls, pathways, roofs and architectural details. Explore panels, cladding, steps, kerbs, mosaics and veneers alongside our wider stone range. We supply, manufacture and export natural stone products from India.</p>
      </div>
      <div class="category-grid">
        @foreach([
          ['name' => 'Stone Wall Panel', 'slug' => 'index-stone-wall-panel', 'image' => 'assets/img/products/index/wall-panel/kandla-grey-sandstone-wall-panels.jpg'],
          ['name' => 'Stone Wall Cladding', 'slug' => 'index-stone-wall-cladding', 'image' => 'assets/img/products/index/wall-cladding/lalitpur-yellow-sandstone-natural-wall-cladding.jpg'],
          ['name' => 'Natural Stone Step', 'slug' => 'index-stone-step', 'image' => 'assets/img/optimized/d79b24d8c705b87b-800.webp'],
          ['name' => 'Natural Stone Kerb', 'slug' => 'index-stone-kerb', 'image' => 'assets/img/products/index/stone-kerb/mint-sandstone-natural-garden-paving-kerbs.jpg'],
          ['name' => 'Natural Stone Lintel', 'slug' => 'index-stone-lintel', 'image' => 'assets/img/optimized/91f22ee6110e6580-800.webp'],
          ['name' => 'Natural Stepping Stone', 'slug' => 'index-stone-stepping', 'image' => 'assets/img/optimized/618cb9f3d72a9a6f-800.webp'],
          ['name' => 'Natural Stone Palisade', 'slug' => 'index-stone-palisade', 'image' => 'assets/img/optimized/824e38df12a9e0dd-800.webp'],
          ['name' => 'Natural Crazy Stone', 'slug' => 'index-stone-crazy', 'image' => 'assets/img/optimized/6dfef17b1801217e-800.webp'],
          ['name' => 'Natural Stone Circle', 'slug' => 'index-stone-circle', 'image' => 'assets/img/products/index/stone-circle/lalitpur-yellow-sandstone-natural-finish-circle.jpg'],
          ['name' => 'Stone Pier Cap', 'slug' => 'index-stone-pier-cap', 'image' => 'assets/img/optimized/26ed9ec6ab715a3f-800.webp'],
          ['name' => 'Stone Roof Tile', 'slug' => 'index-stone-roof-tile', 'image' => 'assets/img/optimized/9d56fc85bb11ee41-800.webp'],
          ['name' => 'Natural Monolith Stone', 'slug' => 'index-stone-monolith', 'image' => 'assets/img/products/index/monolith-stone/indian-slate-stone-natural-monoliths.jpg'],
          ['name' => 'Natural Stone Veneer', 'slug' => 'index-stone-veneer', 'image' => 'assets/img/optimized/a94e2016d80aa6c8-800.webp'],
          ['name' => 'Natural Stone Mosaic', 'slug' => 'index-stone-mosaic', 'image' => 'assets/img/optimized/1b160e9473f1bb7e-800.webp'],
        ] as $product)
          <a class="catalogue-card" href="{{ url('/'.$product['slug']) }}">
            <img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" width="800" height="600" loading="lazy" decoding="async">
            <div class="catalogue-card-body">
              <div class="catalogue-card-title">
                <h3>{{ $product['name'] }}</h3>
                <span class="text-link">View stone →</span>
              </div>
            </div>
          </a>
        @endforeach
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
        <a href="{{ url('/project-gallery') }}#project-2" class="project-card"><img src="{{ asset('assets/img/projects/dholpur-beige-sandstone-wall-cladding-building-project.jpg') }}" alt="Beige sandstone on a building facade" width="1200" height="760" loading="lazy" decoding="async"><div><h3>Sandstone wall cladding</h3><p>Natural texture for architectural surfaces.</p></div></a>
        <a href="{{ url('/project-gallery') }}#project-1" class="project-card"><img src="{{ asset('assets/img/projects/black-granite-wall-cladding-slabs-design-cnc-work.jpg') }}" alt="Carved black granite wall cladding" width="800" height="600" loading="lazy" decoding="async"><div><h3>Granite detailing</h3><p>Explore decorative stonework and finishes.</p></div></a>
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
