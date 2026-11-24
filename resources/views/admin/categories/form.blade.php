@extends('admin.layout')

@php
  $isEdit = $category->exists;
  $removeCover = (string) old('remove_cover') === '1';
@endphp

@section('documentTitle', $isEdit ? 'Edit '.$category->name : 'Add category')

@section('content')
<form class="home-studio product-studio" method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <a href="{{ route('admin.categories.index') }}">Categories</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">{{ old('name', $category->name) ?: 'New category' }}</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Catalogue</p>
      <h1>{{ old('name', $category->name) ?: 'New category' }}</h1>
      <p class="home-lead">Name, short description, and cover used on the material page.</p>
    </div>
    <div class="home-studio-actions">
      <a class="home-btn home-btn-ghost" href="{{ route('admin.categories.index') }}">All categories</a>
      @if($isEdit)
        <a class="home-btn home-btn-ghost" href="{{ url('/'.$category->slug) }}" target="_blank" rel="noopener">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
          View on site
        </a>
      @endif
      <button class="home-btn home-btn-primary" type="submit">Save</button>
    </div>
  </div>

  <section class="home-card">
    <div class="home-card-head">
      <div class="home-card-title">
        <span class="home-index">01</span>
        <div>
          <h2>Basics</h2>
          <p>The slug is the public address, such as /sandstone.</p>
        </div>
      </div>
    </div>
    <div class="product-panel-body">
      <div class="product-editor-grid">
        <div class="product-editor-field">
          <label for="name">Name</label>
          <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category->name) }}" required @error('name') aria-invalid="true" @enderror>
          @error('name')<p class="product-editor-hint">{{ $message }}</p>@enderror
        </div>
        <div class="product-editor-field">
          <label for="slug">Slug</label>
          <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="Filled from the name if left blank" @error('slug') aria-invalid="true" @enderror>
          <p class="product-editor-hint">Use letters, numbers, hyphens, or underscores.</p>
          @error('slug')<p class="product-editor-hint">{{ $message }}</p>@enderror
        </div>
        <div class="product-editor-field">
          <label for="position">Position</label>
          <input class="form-control @error('position') is-invalid @enderror" id="position" name="position" type="number" min="0" max="9999" value="{{ old('position', $category->position ?? 0) }}" @error('position') aria-invalid="true" @enderror>
          <p class="product-editor-hint">Lower numbers appear first. The list arrows rewrite this order.</p>
        </div>
      </div>
      <div class="product-editor-field">
        <label for="description">Description</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" maxlength="2000" @error('description') aria-invalid="true" @enderror>{{ old('description', $category->description) }}</textarea>
        <p class="product-editor-hint">Short line for search and the material page when no introduction is set.</p>
      </div>
      <div class="product-editor-field">
        <label for="introduction">Introduction</label>
        <textarea class="form-control @error('introduction') is-invalid @enderror" id="introduction" name="introduction" rows="5" maxlength="5000" @error('introduction') aria-invalid="true" @enderror>{{ old('introduction', $category->introduction) }}</textarea>
        <p class="product-editor-hint">Shown at the top of the public material page.</p>
      </div>
    </div>
  </section>

  <section class="home-card">
    <div class="home-card-head">
      <div class="home-card-title">
        <span class="home-index">02</span>
        <div>
          <h2>Cover</h2>
          <p>JPG, PNG, or WebP. Up to 5 MB.</p>
        </div>
      </div>
    </div>
    <div class="product-panel-body">
      @if($isEdit && $category->image && ! $removeCover)
        <p class="product-catalogue-stone">
          <img src="{{ asset($category->image) }}" alt="">
          <span>Current cover</span>
        </p>
        <label class="product-editor-check">
          <input type="checkbox" name="remove_cover" value="1" @checked($removeCover)>
          Remove the current cover
        </label>
      @endif
      <div class="product-editor-field">
        <label for="cover">{{ $isEdit && $category->image ? 'Replace cover' : 'Cover image' }}</label>
        <input class="form-control @error('cover') is-invalid @enderror" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" @error('cover') aria-invalid="true" @enderror>
        @error('cover')<p class="product-editor-hint">{{ $message }}</p>@enderror
      </div>
    </div>
  </section>
</form>
@endsection
