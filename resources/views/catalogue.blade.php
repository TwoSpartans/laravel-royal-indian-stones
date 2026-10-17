@extends('layouts.site')

@section('content')
<main id="main" class="catalogue-page">
  <section class="catalogue-intro">
    <div class="container">
      <p class="eyebrow">The Royal Indian Stones collection</p>
      <h1>Find the stone for your project</h1>
      <p>Browse by material, colour, finish or application. Open a product to see its specifications and request a quotation.</p>
    </div>
  </section>
  <section class="catalogue-section">
    <div class="container">
      <form id="product_filters" class="catalogue-filters" role="search" aria-label="Search and filter stones">
        <div class="search-field"><label for="product_search">Search products</label><input id="product_search" type="search" name="q" placeholder="Try Raj Green, black granite or pebbles" autocomplete="off" maxlength="120"></div>
        <div><label for="filter_material">Material / type</label><select id="filter_material" name="material"><option value="">All types</option></select></div>
        <div><label for="filter_color">Colour</label><select id="filter_color" name="color"><option value="">All colours</option></select></div>
        <div><label for="filter_finish">Finish</label><select id="filter_finish" name="finish"><option value="">All finishes</option></select></div>
        <div><label for="filter_application">Application</label><select id="filter_application" name="application"><option value="">All applications</option></select></div>
        <button type="reset" class="filter-reset">Clear filters</button>
      </form>
      <div class="catalogue-summary">
        <p id="product_count" role="status" aria-live="polite">Loading products…</p>
        <label for="product_sort">Sort <select id="product_sort"><option value="name">Name: A–Z</option><option value="material">Material</option></select></label>
      </div>
      <div id="product_results" class="product-grid" aria-busy="true"></div>
      <div id="catalogue_empty" class="empty-state" hidden>
        <h2>No matching stones</h2>
        <p>Try a different name or clear a filter. We can also help you find a material for your project.</p>
        <a href="{{ route('contact') }}" class="ris-button">Ask our team</a>
      </div>
      <div class="catalogue-load-more"><button id="load_more_products" type="button" class="ris-button" hidden>Show more products</button></div>
      <noscript>
        <p>Search requires JavaScript. Browse the complete product list below.</p>
        <ul>
          @foreach($products as $product)
            <li><a href="{{ url('/'.$product->slug) }}">{{ $product->name }}</a></li>
          @endforeach
        </ul>
      </noscript>
    </div>
  </section>
</main>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/catalogue-core.js') }}?v=1" defer></script>
<script src="{{ asset('assets/js/catalogue.js') }}?v=2" defer></script>
@endpush
