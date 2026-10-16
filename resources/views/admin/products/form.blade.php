@extends('admin.layout')

@php
  $isEdit = $product->exists;
  $publishedValue = (string) old('is_published', $product->is_published ? '1' : '0');
  $removedIds = array_map(intval(...), (array) old('remove_images', []));
  if (session()->hasOldInput()) {
      $specRows = collect(old('specs', []))->map(fn ($spec) => [
          'label' => $spec['label'] ?? '',
          'value' => $spec['value'] ?? '',
      ]);
  } else {
      $specRows = $isEdit
          ? $product->specs->map(fn ($spec) => ['label' => $spec->label, 'value' => $spec->value])->values()
          : collect();
  }
  $blankSpecRows = $specRows->isEmpty() ? 2 : 1;
  $saveState = match (true) {
      $errors->any() => 'Check the form',
      session()->has('status') => 'Saved',
      ! $isEdit => 'Not saved',
      default => '',
  };
  $images = $isEdit ? $product->images : collect();
  $invalid = function (string $field) use ($errors): string {
      $hasError = $errors->has($field);
      if (! $hasError) {
          foreach ($errors->keys() as $key) {
              if (str_starts_with((string) $key, $field.'.')) {
                  $hasError = true;
                  break;
              }
          }
      }

      return $hasError ? ' is-invalid' : '';
  };
@endphp

@section('pageTitle')
{{ $isEdit ? 'Edit '.$product->name : 'Add product' }}
@endsection
@section('pageDescription')
{{ $isEdit ? 'Update the stone shown in the catalogue.' : 'Add a stone to the catalogue.' }}
@endsection

@section('content')
<form class="product-editor" method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" data-product-studio>
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="product-editor-bar">
    <div class="product-editor-bar-copy">
      <strong data-bar-name>{{ old('name', $product->name) ?: 'New stone' }}</strong>
      <span class="product-editor-visibility" data-bar-visibility>{{ $publishedValue === '1' ? 'Shown' : 'Hidden' }}</span>
    </div>
    <p class="product-editor-save-state" data-save-state role="status">{{ $saveState }}</p>
    <div class="product-editor-bar-actions">
      <button class="product-editor-text-btn" type="button" data-open-preview>Preview</button>
      @if($isEdit)
        <button class="product-editor-text-btn" type="submit" form="product-duplicate-form">Duplicate</button>
      @endif
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
    <div class="product-editor-main">
      <section class="product-editor-section" id="photos" data-section="photos">
        <p class="product-editor-step">01</p>
        <h2>Photos</h2>
        <p class="product-editor-lede">The first picture is the catalogue cover. Drag a handle to change the order, or use the arrow buttons.</p>
        <p class="visually-hidden" data-order-status aria-live="polite"></p>

        <div class="product-editor-photos" data-photo-list>
          @foreach($images as $image)
            @php
              $detail = $imageDetails[$image->id] ?? ['label' => null];
              $meta = collect([$image->width && $image->height ? $image->width.' × '.$image->height : null, $detail['label'] ?? null])->filter()->implode(' · ');
            @endphp
            <article class="product-editor-photo {{ in_array($image->id, $removedIds, true) ? 'is-removed' : '' }}" data-photo-card data-image-id="{{ $image->id }}">
              <div class="product-editor-photo-tools">
                <button type="button" class="product-editor-handle" draggable="true" data-drag-handle aria-label="Drag to reorder">
                  <i class="bi bi-grip-vertical"></i>
                </button>
                <span class="product-editor-cover" data-cover-badge hidden>Cover</span>
                <button type="button" data-make-cover>Make cover</button>
              </div>
              <button type="button" class="product-editor-photo-open" data-open-photo>
                <img src="{{ asset($image->path) }}" alt="" @if($image->width) width="{{ $image->width }}" @endif @if($image->height) height="{{ $image->height }}" @endif>
              </button>
              @if($meta !== '')
                <p class="product-editor-meta">{{ $meta }}</p>
              @endif
              <label for="image-alt-{{ $image->id }}">Alt text</label>
              <input class="form-control" id="image-alt-{{ $image->id }}" data-photo-alt name="existing_images[{{ $image->id }}][alt]" value="{{ old('existing_images.'.$image->id.'.alt', $image->alt) }}" maxlength="255">
              <input type="hidden" data-photo-position name="existing_images[{{ $image->id }}][position]" value="{{ old('existing_images.'.$image->id.'.position', $image->position) }}">
              <div class="product-editor-photo-actions">
                <button type="button" data-move-up aria-label="Move earlier"><i class="bi bi-arrow-up"></i></button>
                <button type="button" data-move-down aria-label="Move later"><i class="bi bi-arrow-down"></i></button>
                <label class="product-editor-remove">
                  <input type="checkbox" data-remove-image name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, $removedIds, true))>
                  <span data-remove-label>{{ in_array($image->id, $removedIds, true) ? 'Will be removed' : 'Remove' }}</span>
                </label>
              </div>
            </article>
          @endforeach
        </div>

        <div class="product-editor-drop" data-drop-zone>
          <label for="new-photos">Add photos</label>
          <input id="new-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="{{ trim($invalid('photos')) }}" @if($invalid('photos') !== '') aria-invalid="true" @endif>
          <p class="product-editor-hint">Drop JPG, PNG, or WebP files here, up to 5 MB each. They are stored when you save.</p>
          <button class="product-editor-add" type="button" data-pick-photos>Add photos</button>
          <ul class="product-editor-upload-errors" data-upload-errors></ul>
          <div class="product-editor-preview" data-photo-preview></div>
        </div>
      </section>

      <section class="product-editor-section" id="basics" data-section="basics">
        <p class="product-editor-step">02</p>
        <h2>Basics</h2>
        <p class="product-editor-lede">Name and material are required. Finishes and applications feed the catalogue filters.</p>

        <div class="product-editor-grid">
          <div class="product-editor-field">
            <label for="name">Name</label>
            <input class="form-control{{ $invalid('name') }}" id="name" name="name" value="{{ old('name', $product->name) }}" required data-product-name @if($invalid('name') !== '') aria-invalid="true" @endif>
          </div>
          <div class="product-editor-field">
            <label for="slug">Slug</label>
            <input class="form-control{{ $invalid('slug') }}" id="slug" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="Filled from the name if left blank" data-product-slug @if(! $isEdit && ! session()->hasOldInput()) data-slug-follows-name @endif @if($invalid('slug') !== '') aria-invalid="true" @endif>
            <p class="product-editor-hint" data-slug-hint>Use letters, numbers, hyphens, or underscores.</p>
          </div>
          <div class="product-editor-field">
            <label for="category_id">Material</label>
            <select class="form-select{{ $invalid('category_id') }}" id="category_id" name="category_id" required @if($invalid('category_id') !== '') aria-invalid="true" @endif>
              <option value="" disabled @selected(old('category_id', $product->category_id) === null)>Select a material</option>
              @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="product-editor-field">
            <label for="color">Colour</label>
            <input class="form-control{{ $invalid('color') }}" id="color" name="color" value="{{ old('color', $product->color) }}" @if($invalid('color') !== '') aria-invalid="true" @endif>
          </div>
          <div class="product-editor-field">
            <label for="finishes_text">Finishes</label>
            <input class="form-control{{ $invalid('finishes_text') }}" id="finishes_text" name="finishes_text" value="{{ old('finishes_text', implode(', ', $product->finishes ?? [])) }}" placeholder="Honed, Polished" @if($invalid('finishes_text') !== '') aria-invalid="true" @endif>
            <p class="product-editor-hint">Separate with commas.</p>
          </div>
          <div class="product-editor-field">
            <label for="applications_text">Applications</label>
            <input class="form-control{{ $invalid('applications_text') }}" id="applications_text" name="applications_text" value="{{ old('applications_text', implode(', ', $product->applications ?? [])) }}" placeholder="Paving, Wall cladding" @if($invalid('applications_text') !== '') aria-invalid="true" @endif>
            <p class="product-editor-hint">Separate with commas.</p>
          </div>
        </div>

        <input type="hidden" name="is_published" value="0">
        <label class="product-editor-check">
          <input type="checkbox" name="is_published" value="1" data-published @checked($publishedValue === '1')>
          Show this stone on the site
        </label>
      </section>

      <section class="product-editor-section" id="specifications" data-section="specifications">
        <p class="product-editor-step">03</p>
        <h2>Specifications</h2>
        <p class="product-editor-lede">Each complete row becomes a line in the table on the product page. Empty rows are ignored.</p>

        <div class="product-editor-template">
          <label for="spec-template">Suggested labels</label>
          <select class="form-select" id="spec-template" data-spec-template>
            <option value="">Choose a material template</option>
            @foreach($specTemplates as $material => $labels)
              <option value="{{ $material }}">{{ $material }}</option>
            @endforeach
          </select>
          <button class="product-editor-add" type="button" data-apply-template>Add missing labels</button>
        </div>
        <script type="application/json" data-spec-templates>@json($specTemplates)</script>

        <div class="product-editor-spec-head" aria-hidden="true">
          <span></span>
          <span>Label</span>
          <span>Value</span>
        </div>
        <div data-spec-list>
          @foreach($specRows as $index => $spec)
            <div class="product-editor-spec">
              <button type="button" class="product-editor-handle" draggable="true" data-spec-handle aria-label="Drag specification"><i class="bi bi-grip-vertical"></i></button>
              <input class="form-control" data-spec-label name="specs[{{ $index }}][label]" value="{{ $spec['label'] }}" placeholder="Label" aria-label="Specification label">
              <input class="form-control" data-spec-value name="specs[{{ $index }}][value]" value="{{ $spec['value'] }}" placeholder="Value" aria-label="Specification value">
              <div class="product-editor-spec-actions">
                <button type="button" data-spec-up aria-label="Move specification up"><i class="bi bi-arrow-up"></i></button>
                <button type="button" data-spec-down aria-label="Move specification down"><i class="bi bi-arrow-down"></i></button>
                <button type="button" data-duplicate-spec>Duplicate</button>
                <button type="button" data-remove-spec>Remove</button>
              </div>
            </div>
          @endforeach
          @for($index = $specRows->count(); $index < $specRows->count() + $blankSpecRows; $index++)
            <div class="product-editor-spec">
              <button type="button" class="product-editor-handle" draggable="true" data-spec-handle aria-label="Drag specification"><i class="bi bi-grip-vertical"></i></button>
              <input class="form-control" data-spec-label name="specs[{{ $index }}][label]" placeholder="Label" aria-label="Specification label">
              <input class="form-control" data-spec-value name="specs[{{ $index }}][value]" placeholder="Value" aria-label="Specification value">
              <div class="product-editor-spec-actions">
                <button type="button" data-spec-up aria-label="Move specification up"><i class="bi bi-arrow-up"></i></button>
                <button type="button" data-spec-down aria-label="Move specification down"><i class="bi bi-arrow-down"></i></button>
                <button type="button" data-duplicate-spec>Duplicate</button>
                <button type="button" data-remove-spec>Remove</button>
              </div>
            </div>
          @endfor
        </div>
        <button class="product-editor-add" type="button" data-add-spec>Add specification</button>
        <template id="spec-row-template">
          <div class="product-editor-spec">
            <button type="button" class="product-editor-handle" draggable="true" data-spec-handle aria-label="Drag specification"><i class="bi bi-grip-vertical"></i></button>
            <input class="form-control" data-spec-label name="specs[__INDEX__][label]" placeholder="Label" aria-label="Specification label">
            <input class="form-control" data-spec-value name="specs[__INDEX__][value]" placeholder="Value" aria-label="Specification value">
            <div class="product-editor-spec-actions">
              <button type="button" data-spec-up aria-label="Move specification up"><i class="bi bi-arrow-up"></i></button>
              <button type="button" data-spec-down aria-label="Move specification down"><i class="bi bi-arrow-down"></i></button>
              <button type="button" data-duplicate-spec>Duplicate</button>
              <button type="button" data-remove-spec>Remove</button>
            </div>
          </div>
        </template>
      </section>

      <section class="product-editor-section" id="story" data-section="story">
        <p class="product-editor-step">04</p>
        <h2>Story</h2>
        <p class="product-editor-lede">A blank line starts a new paragraph on the product page. Closing a panel keeps the text.</p>

        <div class="product-editor-story">
          <div>
            <details open>
              <summary>Summary</summary>
              <label for="summary">Summary</label>
              <textarea class="form-control{{ $invalid('summary') }}" id="summary" name="summary" rows="3" maxlength="180" data-story-summary @if($invalid('summary') !== '') aria-invalid="true" @endif>{{ old('summary', $product->summary) }}</textarea>
              <p class="product-editor-hint"><span data-summary-count>0</span> / 180</p>
            </details>
            <details open>
              <summary>Product detail</summary>
              <label for="detail">Product detail</label>
              <textarea class="form-control{{ $invalid('detail') }}" id="detail" name="detail" rows="6" data-story-detail @if($invalid('detail') !== '') aria-invalid="true" @endif>{{ old('detail', $product->detail) }}</textarea>
            </details>
            <details open>
              <summary>Application areas</summary>
              <label for="application">Application areas</label>
              <textarea class="form-control{{ $invalid('application') }}" id="application" name="application" rows="5" data-story-application @if($invalid('application') !== '') aria-invalid="true" @endif>{{ old('application', $product->application) }}</textarea>
            </details>
          </div>
          <div class="product-editor-story-preview" data-story-preview></div>
        </div>
      </section>

      <section class="product-editor-section" id="search" data-section="search">
        <p class="product-editor-step">05</p>
        <h2>Search</h2>
        <p class="product-editor-lede">Character counts are guidance for a search snippet. They do not control how a search engine displays the page.</p>

        <div class="product-editor-field">
          <label for="meta_title">Page title</label>
          <input class="form-control{{ $invalid('meta_title') }}" id="meta_title" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" maxlength="255" @if($invalid('meta_title') !== '') aria-invalid="true" @endif>
          <p class="product-editor-hint" data-title-count></p>
        </div>
        <div class="product-editor-field">
          <label for="meta_description">Page description</label>
          <textarea class="form-control{{ $invalid('meta_description') }}" id="meta_description" name="meta_description" rows="3" maxlength="2000" @if($invalid('meta_description') !== '') aria-invalid="true" @endif>{{ old('meta_description', $product->meta_description) }}</textarea>
          <p class="product-editor-hint" data-description-count></p>
        </div>
        <div class="product-seo-snippet" data-seo-snippet>
          <p data-seo-title></p>
          <p data-seo-url></p>
          <p data-seo-description></p>
        </div>
      </section>
    </div>

    <aside class="product-editor-rail">
      <nav aria-label="Product sections">
        <a href="#photos" data-section-link="photos">Photos <span data-section-state="photos"></span></a>
        <a href="#basics" data-section-link="basics">Basics <span data-section-state="basics"></span></a>
        <a href="#specifications" data-section-link="specifications">Specifications <span data-section-state="specs"></span></a>
        <a href="#story" data-section-link="story">Story <span data-section-state="story"></span></a>
        <a href="#search" data-section-link="search">Search <span data-section-state="search"></span></a>
      </nav>
      <div class="product-editor-checklist">
        <p>Readiness</p>
        <button type="button" data-goto="name"><span data-check="name"></span> Name</button>
        <button type="button" data-goto="category_id"><span data-check="category"></span> Material</button>
        <button type="button" data-goto="photos"><span data-check="photos"></span> At least one photo</button>
        <button type="button" data-goto="photos"><span data-check="alts"></span> Alt text on photos</button>
        <button type="button" data-goto="specifications"><span data-check="specs"></span> A specification</button>
        <button type="button" data-goto="summary"><span data-check="summary"></span> Summary</button>
        <button type="button" data-goto="meta_title"><span data-check="title"></span> Page title</button>
        <button type="button" data-goto="meta_description"><span data-check="description"></span> Page description</button>
      </div>
    </aside>
  </div>
</form>

@if($isEdit)
  <form id="product-duplicate-form" method="post" action="{{ route('admin.products.duplicate', $product) }}" data-duplicate-form>
    @csrf
  </form>
@endif

<div class="product-preview" data-preview hidden>
  <div class="product-preview-bar">
    <strong>Preview</strong>
    <button type="button" data-preview-desktop aria-pressed="true">Desktop</button>
    <button type="button" data-preview-mobile aria-pressed="false">Mobile</button>
    <button type="button" data-preview-close>Back to editing</button>
  </div>
  <div class="product-preview-scroll">
    <article class="product-preview-frame is-desktop" data-preview-frame></article>
  </div>
</div>

<div class="product-studio-overlay" data-photo-overlay hidden>
  <div class="product-studio-dialog" role="dialog" aria-modal="true" aria-labelledby="photo-overlay-title">
    <h2 id="photo-overlay-title">Photo</h2>
    <img data-overlay-image alt="">
    <label for="overlay-alt">Alt text</label>
    <input class="form-control" id="overlay-alt" data-overlay-alt>
    <div class="product-editor-photo-actions">
      <button type="button" data-overlay-cover>Make cover</button>
      <button type="button" data-overlay-up>Earlier</button>
      <button type="button" data-overlay-down>Later</button>
      <button type="button" data-overlay-remove>Remove</button>
      <button type="button" data-overlay-close>Close</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/product-studio.js') }}?v=3"></script>
@endpush
