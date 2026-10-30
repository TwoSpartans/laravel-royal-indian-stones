@extends('admin.layout')

@php
  $sectionLabels = [
      'hero' => 'Opening slides',
      'supplier' => 'Supplier line',
      'explore' => 'Explore collection',
      'stones' => 'Natural Indian Stones',
      'journal' => 'Journal',
      'enquiry' => 'Closing band',
  ];
  $nav = [];
  foreach ($groups as $index => $group) {
      $nav[] = [
          'key' => $group['key'],
          'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
          'label' => $sectionLabels[$group['key']] ?? $group['title'],
      ];
  }
  $knownSections = array_column($nav, 'key');
  $openSection = session('home_section');
  if (! is_string($openSection) || ! in_array($openSection, $knownSections, true)) {
      $openSection = 'hero';
  }
  $heroSlot = '';
  $heroTab = 'content';
  $heroErrors = [];
  $tabForField = [
      'photo' => 'image',
      'restore' => 'image',
      'link' => 'content',
      'alt' => 'content',
  ];
  $firstErrorSection = null;
  foreach ($errors->keys() as $key) {
      $parts = explode('.', $key);
      $section = $parts[1] ?? '';
      if (! in_array($section, $knownSections, true)) {
          continue;
      }
      $firstErrorSection ??= $section;
      if ($section !== 'hero') {
          continue;
      }
      $slot = $parts[2] ?? '';
      $field = $parts[0] ?? '';
      if ($slot === '' || $field === '') {
          continue;
      }
      $heroErrors[$slot][] = $field;
      if ($heroSlot === '') {
          $heroSlot = $slot;
          $heroTab = $tabForField[$field] ?? 'content';
      }
  }
  if (is_string($firstErrorSection)) {
      $openSection = $firstErrorSection;
  }
  foreach ($heroErrors as $slot => $fields) {
      $heroErrors[$slot] = array_values(array_unique($fields));
  }
  $crumb = collect($nav)->firstWhere('key', $openSection);
  $heroOnlyErrors = $errors->any();
  foreach ($errors->keys() as $key) {
      $parts = explode('.', $key);
      if (($parts[1] ?? '') !== 'hero') {
          $heroOnlyErrors = false;
          break;
      }
  }
  $previewHost = parse_url((string) config('app.url'), PHP_URL_HOST);
  if (! is_string($previewHost) || $previewHost === '') {
      $previewHost = request()->getHost();
  }
@endphp

@section('documentTitle', 'Home page editor')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<script>
document.documentElement.classList.add('home-editor-enhanced');
@if (! $errors->any())
(function () {
  var hash = location.hash.replace(/^#/, '');
  var allowed = @json($knownSections);
  if (allowed.indexOf(hash) !== -1) {
    document.documentElement.dataset.homeSection = hash;
  }
})();
@endif
</script>
@endpush

@section('content')
<div class="home-studio" data-home-studio data-open-section="{{ $openSection }}" data-lock="{{ $errors->any() ? '1' : '0' }}">
  <nav class="home-path" aria-label="Breadcrumb">
    <span>Content</span>
    <span aria-hidden="true">/</span>
    <a href="{{ route('admin.home.edit') }}">Home page editor</a>
    <span aria-hidden="true">/</span>
    <span data-crumb aria-current="page">{{ $crumb['label'] ?? 'Opening slides' }}</span>
  </nav>

  <div class="home-studio-top">
    <div>
      <p class="home-kicker">Royal Indian Stones / Content management</p>
      <h1>Home page editor</h1>
      <p class="home-lead">Edit each section without changing the order or structure of your public home page.</p>
    </div>
    <div class="home-studio-actions">
      <p class="home-save-state{{ $errors->any() ? ' is-error' : '' }}" data-save-state role="status">
        <i class="bi {{ $errors->any() ? 'bi-exclamation-circle' : 'bi-check-circle' }}" data-save-icon aria-hidden="true"></i>
        <span data-save-label>{{ $errors->any() ? 'Check the highlighted fields' : 'All changes saved' }}</span>
      </p>
      <a class="home-btn home-btn-ghost" href="{{ route('home') }}" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        View home page
      </a>
    </div>
  </div>

  <div class="home-section-nav" role="tablist" aria-label="Home page sections" data-section-nav>
    @foreach($nav as $item)
      <button
        type="button"
        class="home-section-tab"
        role="tab"
        id="home-tab-{{ $item['key'] }}"
        data-section-tab="{{ $item['key'] }}"
        data-label="{{ $item['label'] }}"
        aria-controls="home-panel-{{ $item['key'] }}"
        aria-selected="{{ $openSection === $item['key'] ? 'true' : 'false' }}"
        tabindex="{{ $openSection === $item['key'] ? '0' : '-1' }}"
      >
        <span class="home-section-index">{{ $item['number'] }}</span>
        {{ $item['label'] }}
      </button>
    @endforeach
  </div>

  @if($errors->any())
    <div class="home-alert" role="alert" tabindex="-1" data-home-errors data-hero-only="{{ $heroOnlyErrors ? '1' : '0' }}">
      <strong>Check the highlighted fields.</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @foreach($groups as $group)
    @if($group['kind'] === 'slides')
      @include('admin.home.opening-slides', [
          'group' => $group,
          'openSection' => $openSection,
          'heroSlot' => $heroSlot,
          'heroTab' => $heroTab,
          'heroErrors' => $heroErrors,
          'previewHost' => $previewHost,
      ])
    @elseif($group['key'] === 'supplier')
      @include('admin.home.supplier-line', [
          'group' => $group,
          'openSection' => $openSection,
      ])
    @elseif($group['key'] === 'explore')
      @include('admin.home.explore-collection', [
          'group' => $group,
          'openSection' => $openSection,
      ])
    @elseif($group['key'] === 'stones')
      @include('admin.home.stone-applications', [
          'group' => $group,
          'openSection' => $openSection,
      ])
    @else
      <form id="home-panel-{{ $group['key'] }}" class="home-editor-form home-panel{{ $openSection === $group['key'] ? ' is-open' : '' }}" method="post" enctype="multipart/form-data" action="{{ route('admin.home.update', $group['key']) }}" data-home-panel="{{ $group['key'] }}"@if($openSection === $group['key']) data-open="1"@endif role="tabpanel" aria-labelledby="home-tab-{{ $group['key'] }}">
        @csrf
        @method('PUT')
        <section class="admin-panel home-editor-panel" aria-labelledby="home-{{ $group['key'] }}-title">
          <div class="admin-panel-head">
            <h2 id="home-{{ $group['key'] }}-title">{{ $group['title'] }}</h2>
          </div>
          <div class="home-editor-body">
            <p>{{ $group['intro'] }}</p>
            @if($group['fields'] !== [])
              <div class="home-editor-fields">
                @foreach($group['fields'] as $field)
                  <div>
                    <label class="form-label" for="{{ $group['key'] }}-{{ $field['name'] }}">{{ $field['label'] }}</label>
                    @if($field['multiline'])
                      <textarea class="form-control" id="{{ $group['key'] }}-{{ $field['name'] }}" name="copy[{{ $group['key'] }}][{{ $field['name'] }}]" rows="4" maxlength="{{ $field['max'] }}">{{ old('copy.'.$group['key'].'.'.$field['name'], $field['value']) }}</textarea>
                    @else
                      <input class="form-control" id="{{ $group['key'] }}-{{ $field['name'] }}" name="copy[{{ $group['key'] }}][{{ $field['name'] }}]" value="{{ old('copy.'.$group['key'].'.'.$field['name'], $field['value']) }}" maxlength="{{ $field['max'] }}">
                    @endif
                  </div>
                @endforeach
              </div>
            @endif
            @if($group['items'] === [])
              @if($group['kind'] !== 'copy')
                <p class="home-editor-empty">Nothing to show in this section yet.</p>
              @endif
            @else
              <div class="home-image-grid">
                @foreach($group['items'] as $item)
                  <article class="home-image-card">
                    @if($item['image'] !== '')
                      <img src="{{ asset($item['image']) }}" alt="">
                    @else
                      <div class="home-image-missing">No photograph yet</div>
                    @endif
                    <div class="home-image-copy">
                      <h3>{{ $item['heading'] }}</h3>
                      @if($group['kind'] === 'explore')
                        <p>{{ $item['detail'] }}</p>
                      @endif
                      <p class="home-image-state">{{ $item['custom'] ? 'Replaced' : 'Original photograph' }}</p>
                    </div>
                    @if($group['kind'] === 'stones')
                      <div>
                        <label class="form-label" for="name-{{ $group['key'] }}-{{ $item['slot'] }}">Name</label>
                        <input class="form-control" id="name-{{ $group['key'] }}-{{ $item['slot'] }}" name="title[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('title.'.$group['key'].'.'.$item['slot'], $item['name']) }}" maxlength="160">
                      </div>
                      <div>
                        <label class="form-label" for="link-{{ $group['key'] }}-{{ $item['slot'] }}">Page</label>
                        <input class="form-control" id="link-{{ $group['key'] }}-{{ $item['slot'] }}" name="link[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('link.'.$group['key'].'.'.$item['slot'], $item['url']) }}" maxlength="200">
                      </div>
                    @endif
                    <div>
                      <label class="form-label" for="alt-{{ $group['key'] }}-{{ $item['slot'] }}">Description</label>
                      <input class="form-control" id="alt-{{ $group['key'] }}-{{ $item['slot'] }}" name="alt[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('alt.'.$group['key'].'.'.$item['slot'], $item['alt']) }}" maxlength="180">
                    </div>
                    <div>
                      <label class="form-label" for="photo-{{ $group['key'] }}-{{ $item['slot'] }}">Replace photograph</label>
                      <input class="form-control" id="photo-{{ $group['key'] }}-{{ $item['slot'] }}" name="photo[{{ $group['key'] }}][{{ $item['slot'] }}]" type="file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    @if($item['custom'])
                      <label class="form-check-label">
                        <input type="checkbox" name="restore[{{ $group['key'] }}][{{ $item['slot'] }}]" value="1" @checked(old('restore.'.$group['key'].'.'.$item['slot']))>
                        Use the original photograph
                      </label>
                    @endif
                  </article>
                @endforeach
              </div>
            @endif
            <button class="admin-primary-btn" type="submit">{{ $group['save'] }}</button>
          </div>
        </section>
      </form>
    @endif
  @endforeach
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/home-editor.js') }}?v=7"></script>
@endpush
