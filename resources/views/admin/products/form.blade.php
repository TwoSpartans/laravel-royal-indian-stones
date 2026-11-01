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
  $blankSpecRows = $specRows->isEmpty() ? 2 : 0;
  $images = $isEdit ? $product->images : collect();
  $sections = [
      'basics' => ['label' => 'Basics', 'mark' => '01'],
      'photos' => ['label' => 'Photos', 'mark' => '02'],
      'specifications' => ['label' => 'Specifications', 'mark' => '03'],
      'story' => ['label' => 'Story', 'mark' => '04'],
      'search' => ['label' => 'Search', 'mark' => '05'],
  ];
  $errorTabs = [
      'name' => 'basics',
      'slug' => 'basics',
      'category_id' => 'basics',
      'color' => 'basics',
      'finishes_text' => 'basics',
      'applications_text' => 'basics',
      'is_published' => 'basics',
      'photos' => 'photos',
      'existing_images' => 'photos',
      'remove_images' => 'photos',
      'photo_alts' => 'photos',
      'specs' => 'specifications',
      'summary' => 'story',
      'detail' => 'story',
      'application' => 'story',
      'meta_title' => 'search',
      'meta_description' => 'search',
  ];
  $openSection = 'basics';
  foreach ($errors->keys() as $key) {
      $root = explode('.', (string) $key)[0];
      if (isset($errorTabs[$root])) {
          $openSection = $errorTabs[$root];
          break;
      }
  }
  $saveMode = match (true) {
      $errors->any() => 'error',
      session()->has('status') => 'saved',
      ! $isEdit => 'fresh',
      default => 'saved',
  };
  $saveLabel = match ($saveMode) {
      'error' => 'Check the highlighted fields',
      'fresh' => 'Not saved',
      default => 'All changes saved',
  };
  $saveIcon = match ($saveMode) {
      'error' => 'bi-exclamation-circle',
      'fresh' => 'bi-circle',
      default => 'bi-check-circle',
  };
  $stoneName = old('name', $product->name) ?: 'New stone';
  $selectedCategoryId = (int) old('category_id', $product->category_id);
  $materialName = $categories->firstWhere('id', $selectedCategoryId)?->name;
  $hasMaterialTemplate = is_string($materialName) && array_key_exists($materialName, $specTemplates);
  $materialButtonLabel = match (true) {
      $hasMaterialTemplate => 'Add '.$materialName.' labels',
      is_string($materialName) && $materialName !== '' => 'No suggested labels for '.$materialName,
      default => 'Select a material first',
  };
  $issueTarget = function (string $key): string {
      $root = explode('.', $key)[0];

      return match ($root) {
          'slug' => 'slug',
          'category_id' => 'category_id',
          'color' => 'color',
          'finishes_text' => 'finishes_text',
          'applications_text' => 'applications_text',
          'photos', 'existing_images', 'remove_images', 'photo_alts' => 'photos',
          'specs' => 'specifications',
          'summary' => 'summary',
          'detail' => 'detail',
          'application' => 'application',
          'meta_title' => 'meta_title',
          'meta_description' => 'meta_description',
          default => 'name',
      };
  };
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

@section('documentTitle', $isEdit ? 'Edit '.$product->name : 'Add product')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<script>
document.documentElement.classList.add('product-editor-enhanced');
@if (! $errors->any())
(function () {
  var hash = location.hash.replace(/^#/, '');
  var allowed = @json(array_keys($sections));
  if (allowed.indexOf(hash) !== -1) {
    document.documentElement.dataset.productSection = hash;
  }
})();
@endif
</script>
@endpush

@section('content')
<form class="home-studio product-studio product-editor" method="post" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" data-product-studio data-open-section="{{ $openSection }}" data-lock="{{ $errors->any() ? '1' : '0' }}" data-save-mode="{{ $saveMode }}">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <nav class="home-path" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}">Admin</a>
    <span aria-hidden="true">/</span>
    <span>Catalogue</span>
    <span aria-hidden="true">/</span>
    <a href="{{ route('admin.products.index') }}">Products</a>
    <span aria-hidden="true">/</span>
    <span data-bar-name>{{ $stoneName }}</span>
    <span aria-hidden="true">/</span>
    <span data-crumb aria-current="page">{{ $sections[$openSection]['label'] }}</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Catalogue</p>
      <div class="product-studio-heading">
        <h1 data-bar-name>{{ $stoneName }}</h1>
        <span class="product-visibility {{ $publishedValue === '1' ? 'is-shown' : 'is-hidden' }}" data-bar-visibility>{{ $publishedValue === '1' ? 'Shown' : 'Hidden' }}</span>
      </div>
      <p class="home-lead">Work on one part of the stone at a time. Save stores every tab together.</p>
    </div>
    <div class="home-studio-actions">
      <p class="home-save-state is-{{ $saveMode }}" data-save-state role="status">
        <i class="bi {{ $saveIcon }}" data-save-icon aria-hidden="true"></i>
        <span data-save-label>{{ $saveLabel }}</span>
      </p>
      <a class="home-btn home-btn-ghost" href="{{ route('admin.products.index') }}">All products</a>
      <button class="home-btn home-btn-ghost" type="button" data-open-preview>Preview</button>
      @if($isEdit)
        <button class="home-btn home-btn-ghost" type="submit" form="product-duplicate-form">Duplicate</button>
        <a class="home-btn home-btn-ghost" href="{{ url('/'.$product->slug) }}" target="_blank" rel="noopener">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
          View on site
        </a>
      @endif
      <button class="home-btn home-btn-primary" type="submit" data-save>Save</button>
    </div>
  </div>

  <div class="home-section-nav" role="tablist" aria-label="Product sections" data-section-nav>
    @foreach($sections as $key => $section)
      <button
        type="button"
        class="home-section-tab"
        role="tab"
        id="product-tab-{{ $key }}"
        data-section-tab="{{ $key }}"
        data-label="{{ $section['label'] }}"
        aria-controls="product-panel-{{ $key }}"
        aria-selected="{{ $openSection === $key ? 'true' : 'false' }}"
        tabindex="{{ $openSection === $key ? '0' : '-1' }}"
      >
        <span class="home-section-index">{{ $section['mark'] }}</span>
        {{ $section['label'] }}
      </button>
    @endforeach
  </div>

  <div class="product-studio-grid">
    <div class="product-studio-main">
      <section id="product-panel-photos" class="home-card product-panel{{ $openSection === 'photos' ? ' is-open' : '' }}" data-product-panel="photos" data-section="photos" role="tabpanel" aria-labelledby="product-tab-photos">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">02</span>
            <div>
              <h2 id="photos">Photos</h2>
              <p>The first picture is the catalogue cover. Drag a handle to change the order, or use the arrow buttons.</p>
            </div>
          </div>
        </div>
        <div class="product-panel-body">
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
        </div>
      </section>

      <section id="product-panel-basics" class="home-card product-panel{{ $openSection === 'basics' ? ' is-open' : '' }}" data-product-panel="basics" data-section="basics" role="tabpanel" aria-labelledby="product-tab-basics">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">01</span>
            <div>
              <h2 id="basics">Basics</h2>
              <p>Name and material are required. Finishes and applications feed the catalogue filters.</p>
            </div>
          </div>
        </div>
        <div class="product-panel-body">
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
                  <option value="{{ $category->id }}" @selected($selectedCategoryId === $category->id)>{{ $category->name }}</option>
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
        </div>
      </section>

      <section id="product-panel-specifications" class="home-card product-panel{{ $openSection === 'specifications' ? ' is-open' : '' }}" data-product-panel="specifications" data-section="specifications" role="tabpanel" aria-labelledby="product-tab-specifications">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">03</span>
            <div>
              <h2 id="specifications">Specifications</h2>
              <p>Each complete row becomes a line in the table on the product page. Empty rows are ignored.</p>
            </div>
          </div>
        </div>
        <div class="product-panel-body">
          <div class="product-spec-layout">
            <div>
              <div class="product-editor-template">
                <button class="home-btn home-btn-primary" type="button" data-apply-material @disabled(! $hasMaterialTemplate)>{{ $materialButtonLabel }}</button>
                <div class="product-editor-template-other">
                  <label for="spec-template">Another material</label>
                  <select class="form-select" id="spec-template" data-spec-template>
                    <option value="">Choose a template</option>
                    @foreach($specTemplates as $material => $labels)
                      <option value="{{ $material }}">{{ $material }}</option>
                    @endforeach
                  </select>
                  <button class="product-editor-add" type="button" data-apply-template>Add those labels</button>
                </div>
              </div>
              <script type="application/json" data-spec-templates>@json($specTemplates)</script>

              <div class="product-editor-spec-head" aria-hidden="true">
                <span></span>
                <span>Label</span>
                <span>Value</span>
                <span></span>
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
                      <button type="button" data-duplicate-spec aria-label="Duplicate specification"><i class="bi bi-files"></i></button>
                      <button type="button" data-remove-spec aria-label="Remove specification"><i class="bi bi-trash"></i></button>
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
                      <button type="button" data-duplicate-spec aria-label="Duplicate specification"><i class="bi bi-files"></i></button>
                      <button type="button" data-remove-spec aria-label="Remove specification"><i class="bi bi-trash"></i></button>
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
                    <button type="button" data-duplicate-spec aria-label="Duplicate specification"><i class="bi bi-files"></i></button>
                    <button type="button" data-remove-spec aria-label="Remove specification"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
              </template>
            </div>
            <aside class="product-spec-preview" aria-label="Specification preview">
              <p>On the product page</p>
              <div data-spec-preview></div>
            </aside>
          </div>
        </div>
      </section>

      <section id="product-panel-story" class="home-card product-panel{{ $openSection === 'story' ? ' is-open' : '' }}" data-product-panel="story" data-section="story" role="tabpanel" aria-labelledby="product-tab-story">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">04</span>
            <div>
              <h2 id="story">Story</h2>
              <p>A blank line starts a new paragraph on the product page.</p>
            </div>
          </div>
        </div>
        <div class="product-panel-body">
          <div class="product-editor-story">
            <div>
              <label for="summary">Summary</label>
              <textarea class="form-control{{ $invalid('summary') }}" id="summary" name="summary" rows="3" maxlength="180" data-story-summary @if($invalid('summary') !== '') aria-invalid="true" @endif>{{ old('summary', $product->summary) }}</textarea>
              <p class="product-editor-hint"><span data-summary-count>0</span> / 180</p>
              <label for="detail">Product detail</label>
              <textarea class="form-control{{ $invalid('detail') }}" id="detail" name="detail" rows="6" data-story-detail @if($invalid('detail') !== '') aria-invalid="true" @endif>{{ old('detail', $product->detail) }}</textarea>
              <label for="application">Application areas</label>
              <textarea class="form-control{{ $invalid('application') }}" id="application" name="application" rows="5" data-story-application @if($invalid('application') !== '') aria-invalid="true" @endif>{{ old('application', $product->application) }}</textarea>
            </div>
            <div class="product-editor-story-preview" data-story-preview></div>
          </div>
        </div>
      </section>

      <section id="product-panel-search" class="home-card product-panel{{ $openSection === 'search' ? ' is-open' : '' }}" data-product-panel="search" data-section="search" role="tabpanel" aria-labelledby="product-tab-search">
        <div class="home-card-head">
          <div class="home-card-title">
            <span class="home-index">05</span>
            <div>
              <h2 id="search">Search</h2>
              <p>Character counts are guidance for a search snippet. They do not control how a search engine displays the page.</p>
            </div>
          </div>
        </div>
        <div class="product-panel-body">
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
        </div>
      </section>
    </div>
  </div>
</form>

@if($isEdit)
  <form id="product-duplicate-form" method="post" action="{{ route('admin.products.duplicate', $product) }}" data-duplicate-form>
    @csrf
  </form>
@endif

<div class="product-issue-modal" data-issue-modal @unless($errors->any()) hidden @endunless>
  <div class="product-issue-dialog" role="dialog" aria-modal="true" aria-labelledby="product-issue-title">
    <h2 id="product-issue-title">Check the highlighted fields.</h2>
    <p data-issue-client @if($errors->any()) hidden @endif>Name and material have to be filled in before this stone can be saved. Open a line below to fix it.</p>
    @if($errors->any())
      <ul class="product-issue-problems">
        @foreach($errors->getMessages() as $field => $messages)
          @foreach($messages as $message)
            <li><button type="button" data-goto="{{ $issueTarget((string) $field) }}" data-issue-problem>{{ $message }}</button></li>
          @endforeach
        @endforeach
      </ul>
    @endif
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
    <button class="home-btn home-btn-primary" type="button" data-issue-close>Back to editing</button>
  </div>
</div>

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
<script src="{{ asset('assets/js/product-studio.js') }}?v=8"></script>
@endpush
