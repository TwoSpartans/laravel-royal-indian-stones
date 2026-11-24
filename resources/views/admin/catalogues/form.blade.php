@extends('admin.layout')

@php
  $isEdit = $catalogue->exists;
  $removeCover = (string) old('remove_cover') === '1';
  $hasCover = $isEdit && filled($catalogue->cover_path);
  $pageTitle = old('title', $catalogue->title) ?: 'New catalogue';
@endphp

@section('documentTitle', $isEdit ? 'Edit '.$catalogue->title : 'Add catalogue')

@section('content')
<form class="home-studio product-studio catalogue-admin" method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.catalogues.update', $catalogue) : route('admin.catalogues.store') }}">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <a href="{{ route('admin.catalogues.index') }}">PDF catalogues</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">{{ $pageTitle }}</span>
  </nav>

  <div class="catalogue-admin-head">
    <div class="catalogue-admin-title-row">
      <div>
        <p class="home-kicker">Royal Indian Stones / Catalogue</p>
        <h1>{{ $pageTitle }}</h1>
      </div>
      <div class="home-studio-actions">
        <a class="home-btn home-btn-ghost" href="{{ route('admin.catalogues.index') }}">All catalogues</a>
        @if($isEdit && $pdfReady)
          <a class="home-btn home-btn-ghost" href="{{ route('e-catalogue.preview', ['catalogue' => $catalogue->slug]) }}" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            Preview
          </a>
        @endif
        <button class="home-btn home-btn-primary" type="submit">
          <i class="bi bi-check2" aria-hidden="true"></i>
          Save
        </button>
      </div>
    </div>
    <p class="home-lead">A title, an optional cover, and the PDF visitors preview and download.</p>
  </div>

  <section class="home-card catalogue-admin-section">
    <div class="home-card-head">
      <div class="home-card-title">
        <span class="home-index">01</span>
        <div>
          <h2>Details</h2>
          <p>The name visitors see, and the address of the preview page.</p>
        </div>
      </div>
    </div>
    <div class="product-panel-body catalogue-admin-fields">
      <div class="product-editor-grid">
        <div class="product-editor-field">
          <label for="title">Title</label>
          <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $catalogue->title) }}" required @error('title') aria-invalid="true" @enderror>
          @error('title')
            <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
          @else
            <p class="product-editor-hint">Shown on the catalogue card and the preview page.</p>
          @enderror
        </div>
        <div class="product-editor-field">
          <label for="slug">Slug</label>
          <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $catalogue->slug) }}" placeholder="Filled from the title if left blank" @error('slug') aria-invalid="true" @enderror>
          @error('slug')
            <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
          @else
            <p class="product-editor-hint">Used in the preview address. Leave blank to build it from the title.</p>
          @enderror
        </div>
        <div class="product-editor-field">
          <label for="version_label">Year or version</label>
          <input class="form-control @error('version_label') is-invalid @enderror" id="version_label" name="version_label" value="{{ old('version_label', $catalogue->version_label) }}" maxlength="40" placeholder="2026" @error('version_label') aria-invalid="true" @enderror>
          @error('version_label')
            <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
          @else
            <p class="product-editor-hint">Optional. A short label, such as 2026, kept on this catalogue. It is not shown on the public page.</p>
          @enderror
        </div>
        <div class="product-editor-field">
          <label for="position">Position</label>
          <input class="form-control @error('position') is-invalid @enderror" id="position" name="position" type="number" min="0" max="9999" value="{{ old('position', $catalogue->position ?? 0) }}" @error('position') aria-invalid="true" @enderror>
          @error('position')
            <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
          @else
            <p class="product-editor-hint">Lower numbers appear first. You can also reorder from the list.</p>
          @enderror
        </div>
      </div>
      <div class="product-editor-field">
        <label for="description">Short description</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" maxlength="500" @error('description') aria-invalid="true" @enderror>{{ old('description', $catalogue->description) }}</textarea>
        @error('description')
          <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
        @else
          <p class="product-editor-hint">Optional. One or two sentences, up to 500 characters.</p>
        @enderror
      </div>
    </div>
  </section>

  <section class="home-card catalogue-admin-section">
    <div class="home-card-head">
      <div class="home-card-title">
        <span class="home-index">02</span>
        <div>
          <h2>Cover</h2>
          <p>Optional. A portrait JPG, PNG, or WebP, up to 5 MB.</p>
        </div>
      </div>
    </div>
    <div class="product-panel-body">
      <div class="catalogue-admin-media">
        <figure class="catalogue-cover-preview {{ $hasCover && ! $removeCover ? '' : 'is-empty' }} {{ $removeCover ? 'is-removed' : '' }}">
          @if($hasCover)
            <img src="{{ asset($catalogue->cover_path) }}" alt="">
          @else
            <span aria-hidden="true"><i class="bi bi-image"></i></span>
          @endif
          <figcaption>{{ $hasCover ? ($removeCover ? 'Cover will be removed' : 'Current cover') : 'No cover yet' }}</figcaption>
        </figure>
        <div class="catalogue-admin-media-fields">
          @if($hasCover)
            <label class="product-editor-check">
              <input type="checkbox" name="remove_cover" value="1" @checked($removeCover)>
              Remove the current cover
            </label>
          @endif
          <div class="product-editor-field catalogue-admin-file">
            <label for="cover">{{ $hasCover ? 'Replace cover' : 'Cover image' }}</label>
            <input class="form-control @error('cover') is-invalid @enderror" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" @error('cover') aria-invalid="true" @enderror>
            @error('cover')
              <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
            @else
              <p class="product-editor-hint">Leave this empty to keep the current cover. A tall image fits the public card best.</p>
            @enderror
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="home-card catalogue-admin-section">
    <div class="home-card-head">
      <div class="home-card-title">
        <span class="home-index">03</span>
        <div>
          <h2>PDF</h2>
          <p>Required before the catalogue appears on the site. Up to {{ $maxMegabytes }} MB.</p>
        </div>
      </div>
    </div>
    <div class="product-panel-body">
      @if($isEdit && filled($catalogue->pdf_original_name))
        <div class="catalogue-pdf-status {{ $pdfReady ? 'is-ready' : 'is-missing' }}">
          <span class="catalogue-pdf-mark" aria-hidden="true"><i class="bi {{ $pdfReady ? 'bi-file-earmark-check' : 'bi-exclamation-circle' }}"></i></span>
          <div>
            <p class="catalogue-pdf-kicker">{{ $pdfReady ? 'On the public page' : 'Missing from storage' }}</p>
            <p class="catalogue-pdf-name">{{ $catalogue->pdf_original_name }}</p>
            @if($catalogue->pdfSizeLabel())
              <p class="catalogue-pdf-size">{{ $catalogue->pdfSizeLabel() }}</p>
            @endif
            @unless($pdfReady)
              <p class="catalogue-pdf-size">Upload the PDF again. Visitors will not see this catalogue until the file is saved.</p>
            @endunless
          </div>
        </div>
      @else
        <div class="catalogue-pdf-status is-empty">
          <span class="catalogue-pdf-mark" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>
          <div>
            <p class="catalogue-pdf-kicker">Not uploaded</p>
            <p class="catalogue-pdf-name">No PDF yet</p>
            <p class="catalogue-pdf-size">The catalogue stays off the public page until a PDF is saved.</p>
          </div>
        </div>
      @endif
      <div class="product-editor-field catalogue-admin-file">
        <label for="pdf">{{ $isEdit && $pdfReady ? 'Replace PDF' : 'PDF file' }}</label>
        <input class="form-control @error('pdf') is-invalid @enderror" id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" @unless($isEdit && $pdfReady) required @endunless @error('pdf') aria-invalid="true" @enderror>
        @error('pdf')
          <p class="product-editor-hint catalogue-admin-error">{{ $message }}</p>
        @else
          <p class="product-editor-hint">{{ $isEdit && $pdfReady ? 'Leave this empty to keep the current PDF.' : 'PDF only. Up to '.$maxMegabytes.' MB.' }}</p>
        @enderror
      </div>
    </div>
  </section>
</form>
@endsection
