@extends('admin.layout')

@section('documentTitle', 'Categories')

@section('content')
<div class="home-studio product-catalogue">
  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Categories</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Catalogue</p>
      <h1>Categories</h1>
      <p class="home-lead">Materials on the public catalogue. Order here is the order visitors see.</p>
    </div>
    <div class="home-studio-actions">
      <a class="home-btn home-btn-primary" href="{{ route('admin.categories.create') }}">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        Add category
      </a>
    </div>
  </div>

  <p class="blog-catalogue-count">{{ $categories->count() }} {{ $categories->count() === 1 ? 'category' : 'categories' }}</p>

  <div class="home-stone-table-wrap">
    <table class="home-stone-table product-catalogue-table">
      <thead>
        <tr>
          <th>Material</th>
          <th>Stones</th>
          <th>Order</th>
          <th><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($categories as $category)
          <tr>
            <td>
              <a class="product-catalogue-stone" href="{{ route('admin.categories.edit', $category) }}">
                @if($category->image)
                  <img src="{{ asset($category->image) }}" alt="">
                @else
                  <span class="product-catalogue-missing">No cover</span>
                @endif
                <span>{{ $category->name }}</span>
              </a>
            </td>
            <td>{{ $category->products_count }}</td>
            <td>
              <div class="category-order">
                <form method="post" action="{{ route('admin.categories.reorder') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $category->id }}">
                  <input type="hidden" name="direction" value="up">
                  <button class="home-btn home-btn-ghost" type="submit" @disabled($loop->first) aria-label="Move {{ $category->name }} up">
                    <i class="bi bi-arrow-up" aria-hidden="true"></i>
                  </button>
                </form>
                <form method="post" action="{{ route('admin.categories.reorder') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $category->id }}">
                  <input type="hidden" name="direction" value="down">
                  <button class="home-btn home-btn-ghost" type="submit" @disabled($loop->last) aria-label="Move {{ $category->name }} down">
                    <i class="bi bi-arrow-down" aria-hidden="true"></i>
                  </button>
                </form>
              </div>
            </td>
            <td>
              <div class="product-catalogue-actions">
                <a class="product-catalogue-edit" href="{{ route('admin.categories.edit', $category) }}" aria-label="Edit {{ $category->name }}">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                </a>
                @if($category->products_count > 0)
                  <span class="product-editor-hint">Remove its stones first</span>
                @else
                  <form method="post" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?');">
                    @csrf
                    @method('DELETE')
                    <button class="product-catalogue-edit" type="submit" aria-label="Delete {{ $category->name }}">
                      <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="product-catalogue-empty">No categories yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
