@extends('admin.layout')

@php
  $isEdit = $post->exists;
  $publishedValue = (string) old('is_published', $isEdit && $post->is_published ? '1' : '0');
  $saveState = match (true) {
      $errors->any() => 'Check the form',
      session()->has('status') => 'Saved',
      ! $isEdit => 'Not saved',
      default => '',
  };
  $invalid = function (string $field) use ($errors): string {
      return $errors->has($field) ? ' is-invalid' : '';
  };
  $coverRemoved = (string) old('remove_cover') === '1';
@endphp

@section('pageTitle')
{{ $isEdit ? 'Edit '.$post->title : 'Add article' }}
@endsection
@section('pageDescription')
{{ $isEdit ? 'Update the article shown on the blog.' : 'Write an article for the blog.' }}
@endsection

@section('content')
<form class="product-editor journal-editor" method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.posts.update', $post) : route('admin.posts.store') }}" data-journal-studio>
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="journal-editor-bar">
    <div>
      <strong data-bar-name>{{ old('title', $post->title) ?: 'New article' }}</strong>
      <span data-bar-visibility>{{ $publishedValue === '1' ? 'Shown' : 'Hidden' }}</span>
    </div>
    <p data-save-state role="status">{{ $saveState }}</p>
    <div>
      <button class="product-editor-text-btn" type="button" data-open-preview>Preview</button>
      <button class="admin-primary-btn" type="submit" data-save>Save</button>
    </div>
  </div>

  @if($errors->any())
    <div class="product-editor-errors" role="alert">
      <strong>Check the highlighted fields.</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="product-editor-layout">
    <div>
      <section class="product-editor-section" id="cover" data-section="cover">
        <p class="product-editor-step">01</p>
        <h2>Cover</h2>
        <p class="product-editor-lede">The cover appears at the top of the article and on the blog list. JPG, PNG, or WebP, up to 5 MB.</p>
        <div class="journal-cover" data-cover-frame @if(! $post->image || $coverRemoved) hidden @endif>
          @if($post->image)
            <img data-cover-image src="{{ asset($post->image) }}" alt="">
          @else
            <img data-cover-image alt="" hidden>
          @endif
        </div>
        <label class="journal-upload" for="article-cover">Choose a cover</label>
        <input class="form-control{{ $invalid('cover') }}" id="article-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" @if($invalid('cover') !== '') aria-invalid="true" @endif>
        @if($post->image)
          <label class="journal-remove">
            <input type="checkbox" name="remove_cover" value="1" data-remove-cover @checked($coverRemoved)>
            Remove the current cover
          </label>
        @endif
      </section>

      <section class="product-editor-section" id="writing" data-section="writing">
        <p class="product-editor-step">02</p>
        <h2>Writing</h2>
        <p class="product-editor-lede">Write the article here. The public page uses this text as it is saved.</p>
        <div class="journal-toolbar" role="toolbar" aria-label="Formatting">
          <button type="button" data-command="paragraph">Text</button>
          <button type="button" data-command="h2">Heading</button>
          <button type="button" data-command="h3">Subheading</button>
          <button type="button" data-command="bold" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
          <button type="button" data-command="italic" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
          <button type="button" data-command="link" aria-label="Link"><i class="bi bi-link-45deg"></i></button>
          <button type="button" data-command="list" aria-label="List"><i class="bi bi-list-ul"></i></button>
          <button type="button" data-command="quote">Quote</button>
          <button type="button" data-command="image">Image</button>
        </div>
        <div class="journal-writing{{ $invalid('body') }}" id="article-body" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Article" data-writing @if($invalid('body') !== '') aria-invalid="true" @endif>{!! $articleHtml !!}</div>
        <textarea name="body" data-body hidden>{{ $articleHtml }}</textarea>
        <input id="inline-images" name="inline_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
        <p class="product-editor-hint" data-upload-errors role="alert"></p>
      </section>

      <section class="product-editor-section" id="details" data-section="details">
        <p class="product-editor-step">03</p>
        <h2>Details</h2>
        <label for="title">Title</label>
        <input class="form-control{{ $invalid('title') }}" id="title" name="title" value="{{ old('title', $post->title) }}" maxlength="200" required data-title @if($invalid('title') !== '') aria-invalid="true" @endif>
        <div class="journal-detail-grid">
          <div>
            <label for="published_on">Date</label>
            <input class="form-control{{ $invalid('published_on') }}" id="published_on" name="published_on" type="date" value="{{ old('published_on', optional($post->published_on)->toDateString()) }}" data-date>
          </div>
          <div>
            <label for="is_published">Visibility</label>
            <select class="form-select" id="is_published" name="is_published" data-visibility>
              <option value="0" @selected($publishedValue !== '1')>Hidden</option>
              <option value="1" @selected($publishedValue === '1')>Shown</option>
            </select>
          </div>
        </div>
        <label for="post_category_id">Category</label>
        <select class="form-select{{ $invalid('post_category_id') }}" id="post_category_id" name="post_category_id">
          <option value="">None</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('post_category_id', $post->post_category_id) === (string) $category->id)>{{ $category->name }}</option>
          @endforeach
        </select>
        <label for="summary">Summary</label>
        <textarea class="form-control{{ $invalid('summary') }}" id="summary" name="summary" rows="3" maxlength="180">{{ old('summary', $post->summary) }}</textarea>
        <p class="product-editor-hint"><span data-summary-count>0</span> / 180</p>
        <label for="slug">Address</label>
        <input class="form-control{{ $invalid('slug') }}" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="200" placeholder="Leave blank to build it from the title" data-slug @if($invalid('slug') !== '') aria-invalid="true" @endif>
      </section>

      <section class="product-editor-section" id="search" data-section="search">
        <p class="product-editor-step">04</p>
        <h2>Search</h2>
        <p class="product-editor-lede">The description is used when this article is shared or listed by a search engine.</p>
        <label for="meta_description">Description</label>
        <textarea class="form-control{{ $invalid('meta_description') }}" id="meta_description" name="meta_description" rows="3" maxlength="2000" data-description>{{ old('meta_description', $post->meta_description) }}</textarea>
        <div class="journal-snippet" data-seo-snippet>
          <p data-seo-title></p>
          <p data-seo-url></p>
          <p data-seo-description></p>
        </div>
      </section>
    </div>

    <aside class="journal-rail">
      <nav aria-label="Article sections">
        <a href="#cover" data-section-link="cover">Cover</a>
        <a href="#writing" data-section-link="writing">Writing</a>
        <a href="#details" data-section-link="details">Details</a>
        <a href="#search" data-section-link="search">Search</a>
      </nav>
    </aside>
  </div>
</form>

<div class="journal-preview" data-preview hidden>
  <div class="journal-preview-bar">
    <strong>Preview</strong>
    <button type="button" data-preview-desktop aria-pressed="true">Desktop</button>
    <button type="button" data-preview-mobile aria-pressed="false">Mobile</button>
    <button type="button" data-preview-close>Back to editing</button>
  </div>
  <div class="journal-preview-scroll">
    <article class="journal-preview-frame is-desktop" data-preview-frame></article>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/journal-studio.js') }}?v=3"></script>
@endpush
