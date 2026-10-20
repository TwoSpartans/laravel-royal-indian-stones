@extends('layouts.site')

@section('content')
<main id="main" class="shop-page">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>Products</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          <li>Products</li>
        </ol>
      </div>
    </div>
  </section>
  <section class="shop-section">
    <div class="container shop-layout">
      <div id="filter-backdrop" class="shop-filter-backdrop" hidden></div>
      <aside id="filters-sidebar" class="shop-filters" aria-label="Filter stones">
        <button type="button" class="shop-filters-close" aria-label="Close filters">&times;</button>
        <form id="product_filters" role="search">
          <div class="shop-search">
            <label for="product_search">Search products</label>
            <input id="product_search" type="search" name="q" placeholder="Try Raj Green or black granite" autocomplete="off" maxlength="120">
          </div>
          <details id="filter-categories" open>
            <summary>Material</summary>
            <button type="button" id="material_selected" class="shop-selected" hidden></button>
            <input id="material_finder" type="search" placeholder="Search materials" aria-label="Search materials">
            <div id="material_list" class="shop-options" role="listbox"></div>
            <select id="filter_material" name="material" hidden><option value="">All types</option></select>
          </details>
          <details>
            <summary>Colour</summary>
            <div id="color_list" class="shop-options"></div>
            <select id="filter_color" name="color" hidden><option value="">All colours</option></select>
          </details>
          <details id="filter-finish">
            <summary>Finish</summary>
            <input id="finish_finder" type="search" placeholder="Search finishes" aria-label="Search finishes">
            <div id="finish_list" class="shop-options"></div>
            <button type="button" id="finish_more" class="shop-more" hidden></button>
            <select id="filter_finish" name="finish" hidden><option value="">All finishes</option></select>
          </details>
          <details>
            <summary>Application</summary>
            <div id="application_list" class="shop-options"></div>
            <select id="filter_application" name="application" hidden><option value="">All applications</option></select>
          </details>
          <a id="clear_filters" class="filter-reset" href="{{ route('catalogue') }}">Clear filters</a>
        </form>
      </aside>
      <div class="shop-main">
        <p class="eyebrow">The Royal Indian Stones collection</p>
        <h1>Find the stone for your project</h1>
        <div id="material_chips" class="shop-chips" aria-label="Materials"></div>
        <div class="shop-toolbar">
          <p id="product_count" role="status" aria-live="polite">Loading products…</p>
          <div class="shop-toolbar-actions">
            <button type="button" id="filter-toggle-button" class="shop-filter-toggle" aria-expanded="false" aria-controls="filters-sidebar">Filters</button>
            <label for="product_sort">Sort
              <select id="product_sort">
                <option value="name">Name: A–Z</option>
                <option value="material">Material</option>
              </select>
            </label>
          </div>
        </div>
        <div id="product_results" class="product-grid shop-grid" aria-busy="true"></div>
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
    </div>
  </section>
</main>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/catalogue-core.js') }}?v=1" defer></script>
<script src="{{ asset('assets/js/catalogue.js') }}?v=6" defer></script>
@endpush
