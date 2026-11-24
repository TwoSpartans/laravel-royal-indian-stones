@extends('admin.layout')

@section('documentTitle', 'PDF catalogues')

@section('content')
<div class="home-studio product-catalogue catalogue-admin">
  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <span aria-current="page">PDF catalogues</span>
  </nav>

  <div class="catalogue-admin-head">
    <div class="catalogue-admin-title-row">
      <div>
        <p class="home-kicker">Royal Indian Stones / Catalogue</p>
        <h1>PDF catalogues</h1>
      </div>
      <div class="home-studio-actions">
        <a class="home-btn home-btn-primary" href="{{ route('admin.catalogues.create') }}">
          <i class="bi bi-plus-lg" aria-hidden="true"></i>
          Add catalogue
        </a>
      </div>
    </div>
    <p class="home-lead">These appear on the public e-catalogue. Visitors preview each one, then download the PDF. The order here is the order on the site.</p>
  </div>

  <p class="blog-catalogue-count">{{ $catalogues->count() }} {{ $catalogues->count() === 1 ? 'catalogue' : 'catalogues' }}</p>

  @if($catalogues->isEmpty())
    <div class="catalogue-admin-empty">
      <span class="catalogue-admin-empty-mark" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>
      <h2>No catalogues yet</h2>
      <p>Add a title and a PDF. The catalogue appears on the public page once the file is saved.</p>
      <a class="home-btn home-btn-primary" href="{{ route('admin.catalogues.create') }}">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        Add catalogue
      </a>
    </div>
  @else
    <div class="catalogue-admin-list">
      @foreach($catalogues as $catalogue)
        @php
          $pdfReady = $catalogue->pdfFileExists();
          $pdfName = $catalogue->pdf_original_name;
          $status = $pdfReady ? 'is-ready' : (filled($pdfName) ? 'is-missing' : 'is-empty');
        @endphp
        <article class="catalogue-admin-row">
          <a class="catalogue-admin-cover" href="{{ route('admin.catalogues.edit', $catalogue) }}" tabindex="-1" aria-hidden="true">
            @if($catalogue->cover_path)
              <img src="{{ asset($catalogue->cover_path) }}" alt="">
            @else
              <span><i class="bi bi-image"></i></span>
            @endif
          </a>
          <div class="catalogue-admin-main">
            @if($catalogue->version_label)
              <p class="catalogue-admin-version">{{ $catalogue->version_label }}</p>
            @endif
            <h2><a href="{{ route('admin.catalogues.edit', $catalogue) }}">{{ $catalogue->title }}</a></h2>
            @if(filled($catalogue->description))
              <p class="catalogue-admin-summary">{{ $catalogue->description }}</p>
            @endif
            <p class="catalogue-admin-status {{ $status }}">
              <i class="bi {{ $pdfReady ? 'bi-file-earmark-check' : (filled($pdfName) ? 'bi-exclamation-circle' : 'bi-file-earmark') }}" aria-hidden="true"></i>
              <span>
                <strong>{{ $pdfReady ? ($pdfName ?: 'PDF ready') : (filled($pdfName) ? $pdfName : 'No PDF') }}</strong>
                @if($pdfReady && $catalogue->pdfSizeLabel())
                  <span class="catalogue-admin-size">{{ $catalogue->pdfSizeLabel() }}</span>
                @endif
                <em>{{ $pdfReady ? 'On the site' : (filled($pdfName) ? 'Missing file' : 'Hidden') }}</em>
              </span>
            </p>
          </div>
          <div class="catalogue-admin-tools">
            <div class="category-order">
              <span class="visually-hidden">Reorder</span>
              <form method="post" action="{{ route('admin.catalogues.reorder') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $catalogue->id }}">
                <input type="hidden" name="direction" value="up">
                <button class="home-btn home-btn-ghost" type="submit" @disabled($loop->first) aria-label="Move {{ $catalogue->title }} up">
                  <i class="bi bi-arrow-up" aria-hidden="true"></i>
                </button>
              </form>
              <form method="post" action="{{ route('admin.catalogues.reorder') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $catalogue->id }}">
                <input type="hidden" name="direction" value="down">
                <button class="home-btn home-btn-ghost" type="submit" @disabled($loop->last) aria-label="Move {{ $catalogue->title }} down">
                  <i class="bi bi-arrow-down" aria-hidden="true"></i>
                </button>
              </form>
            </div>
            <a class="home-btn home-btn-ghost" href="{{ route('admin.catalogues.edit', $catalogue) }}">
              <i class="bi bi-pencil" aria-hidden="true"></i>
              Edit
            </a>
            <form method="post" action="{{ route('admin.catalogues.destroy', $catalogue) }}" onsubmit="return confirm('Delete this catalogue? Its PDF and cover are removed.');">
              @csrf
              @method('DELETE')
              <button class="home-btn catalogue-admin-delete" type="submit" aria-label="Delete {{ $catalogue->title }}">
                <i class="bi bi-trash" aria-hidden="true"></i>
              </button>
            </form>
          </div>
        </article>
      @endforeach
    </div>
  @endif
</div>
@endsection
