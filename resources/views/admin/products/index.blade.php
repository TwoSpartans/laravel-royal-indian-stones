@extends('admin.layout')

@section('documentTitle', 'Products')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="home-studio product-catalogue">
  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Products</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Catalogue</p>
      <h1>Products</h1>
      <p class="home-lead">Stones shown in the catalogue. Open a stone to edit one part at a time.</p>
    </div>
    <div class="home-studio-actions">
      <a class="home-btn home-btn-primary" href="{{ route('admin.products.create') }}">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        Add product
      </a>
    </div>
  </div>

  <form class="product-catalogue-filter" method="get" action="{{ route('admin.products.index') }}">
    <div>
      <label for="product-search">Name</label>
      <input class="form-control" id="product-search" name="q" value="{{ $search }}" placeholder="Search by name">
    </div>
    <div>
      <label for="product-material">Material</label>
      <select class="form-select" id="product-material" name="category">
        <option value="">All materials</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="product-visibility">Visibility</label>
      <select class="form-select" id="product-visibility" name="shown">
        <option value="" @selected($visibility === '')>All stones</option>
        <option value="shown" @selected($visibility === 'shown')>Shown</option>
        <option value="hidden" @selected($visibility === 'hidden')>Hidden</option>
      </select>
    </div>
    <button class="home-btn home-btn-primary" type="submit">Find</button>
  </form>

  <div class="home-stone-table-wrap">
    <table class="home-stone-table product-catalogue-table">
      <thead>
        <tr>
          <th>Stone</th>
          <th>Material</th>
          <th>Visibility</th>
          <th><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
          @php $cover = $product->catalogueImage(); @endphp
          <tr data-product-row data-href="{{ route('admin.products.edit', $product) }}#basics" tabindex="0">
            <td>
              <a class="product-catalogue-stone" href="{{ route('admin.products.edit', $product) }}#basics">
                @if($cover)
                  <img src="{{ asset($cover->path) }}" alt="">
                @else
                  <span class="product-catalogue-missing">No photo</span>
                @endif
                <span>{{ $product->name }}</span>
              </a>
            </td>
            <td>{{ $product->category?->name }}</td>
            <td>
              <span class="product-visibility {{ $product->is_published ? 'is-shown' : 'is-hidden' }}">{{ $product->is_published ? 'Shown' : 'Hidden' }}</span>
            </td>
            <td>
              <a class="product-catalogue-edit" href="{{ route('admin.products.edit', $product) }}#basics" aria-label="Edit {{ $product->name }}">
                <i class="bi bi-pencil" aria-hidden="true"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="product-catalogue-empty">No stone matched.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  document.querySelectorAll('[data-product-row]').forEach(function (row) {
    row.addEventListener('click', function (event) {
      if (event.target.closest('a, button')) return;
      window.location.assign(row.dataset.href);
    });
    row.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' || event.target.closest('a, button')) return;
      window.location.assign(row.dataset.href);
    });
  });
})();
</script>
@endpush
