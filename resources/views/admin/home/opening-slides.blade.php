@php
  $slots = array_column($group['items'], 'slot');
  $activeSlot = in_array($heroSlot, $slots, true) ? $heroSlot : ($slots[0] ?? '');
  $activeTab = in_array($heroTab, ['content', 'image'], true) ? $heroTab : 'content';
  $activeItem = $group['items'][0] ?? null;
  $activeIndex = 0;
  foreach ($group['items'] as $index => $item) {
      if ($item['slot'] === $activeSlot) {
          $activeItem = $item;
          $activeIndex = $index;
          break;
      }
  }
  $picture = function (mixed $path): string {
      if (! is_string($path) || $path === '') {
          return '';
      }

      return asset($path);
  };
  $display = function (string $slot, string $field, string $saved, string $fallback) use ($group): string {
      $submitted = old($field.'.'.$group['key'].'.'.$slot);
      if (! is_string($submitted)) {
          return $saved;
      }
      $submitted = trim($submitted);

      return $submitted === '' ? $fallback : $submitted;
  };
  $slideCount = count($group['items']);
  $countLabel = str_pad((string) ($activeIndex + 1), 2, '0', STR_PAD_LEFT).' / '.str_pad((string) $slideCount, 2, '0', STR_PAD_LEFT);
  $editorSlides = [];
  foreach ($group['items'] as $index => $item) {
      $defaults = $item['defaults'];
      $editorSlides[] = [
          'slot' => $item['slot'],
          'index' => $index,
          'secondLine' => (bool) $item['second_line'],
          'custom' => (bool) $item['custom'],
          'image' => $picture($item['image']),
          'fallbackImage' => $picture($defaults['image'] ?? ''),
          'saved' => [
              'eyebrow' => $item['eyebrow'],
              'title' => $item['title'],
              'subtitle' => $item['subtitle'],
              'body' => $item['text'],
              'button_label' => $item['label'],
              'link' => $item['url'],
              'alt' => $item['alt'],
          ],
          'defaults' => [
              'eyebrow' => $defaults['eyebrow'],
              'title' => $defaults['title'],
              'subtitle' => $defaults['subtitle'],
              'body' => $defaults['text'],
              'button_label' => $defaults['label'],
              'link' => $defaults['url'],
              'alt' => $defaults['alt'],
          ],
      ];
  }
  $slotErrors = $heroErrors[$activeSlot] ?? [];
  $tabHasError = function (string $tab) use ($slotErrors): bool {
      $fields = [
          'content' => ['eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link', 'alt'],
          'image' => ['photo', 'restore'],
      ];

      return array_intersect($slotErrors, $fields[$tab] ?? []) !== [];
  };
  $previewTitle = $activeItem ? $display($activeSlot, 'title', $activeItem['title'], $activeItem['defaults']['title']) : '';
  $previewSubtitle = $activeItem && $activeItem['second_line']
      ? $display($activeSlot, 'subtitle', $activeItem['subtitle'], $activeItem['defaults']['subtitle'])
      : '';
  $previewEyebrow = $activeItem ? $display($activeSlot, 'eyebrow', $activeItem['eyebrow'], $activeItem['defaults']['eyebrow']) : '';
  $previewBody = $activeItem ? $display($activeSlot, 'body', $activeItem['text'], $activeItem['defaults']['text']) : '';
  $previewButton = $activeItem ? $display($activeSlot, 'button_label', $activeItem['label'], $activeItem['defaults']['label']) : '';
  $previewLink = $activeItem ? $display($activeSlot, 'link', $activeItem['url'], $activeItem['defaults']['url']) : '';
  $previewAlt = $activeItem ? $display($activeSlot, 'alt', $activeItem['alt'], $activeItem['defaults']['alt']) : '';
  $previewSrc = '';
  if ($activeItem) {
      $currentImage = $picture($activeItem['image']);
      $fallbackImage = $picture($activeItem['defaults']['image'] ?? '');
      $restore = old('restore.'.$group['key'].'.'.$activeSlot);
      $useOriginal = is_string($restore) && $restore !== '' && $restore !== '0';
      $previewSrc = $useOriginal ? $fallbackImage : ($currentImage !== '' ? $currentImage : $fallbackImage);
  }
@endphp

<form id="home-panel-hero" class="home-editor-form home-panel home-card{{ $openSection === 'hero' ? ' is-open' : '' }}" method="post" enctype="multipart/form-data" action="{{ route('admin.home.update', $group['key']) }}" data-home-panel="hero"@if($openSection === 'hero') data-open="1"@endif role="tabpanel" aria-labelledby="home-tab-hero" autocomplete="off">
  @csrf
  @method('PUT')
  <div class="home-card-head">
    <div class="home-card-title">
      <span class="home-index">01</span>
      <div>
        <h2 id="home-hero-title">{{ $group['title'] }}</h2>
        <p>{{ $group['intro'] }}</p>
      </div>
    </div>
    <div class="home-card-tools">
      <span class="home-order">{{ $slideCount }} {{ $slideCount === 1 ? 'slide' : 'slides' }} · Homepage order</span>
      <button class="home-btn home-btn-quiet" type="button" data-discard disabled>Discard</button>
      <button class="home-btn home-btn-primary" type="submit" data-save>
        <i class="bi bi-check2" aria-hidden="true"></i>
        Save section
      </button>
    </div>
  </div>

  <div class="home-studio-grid">
    <section class="home-column home-library" aria-labelledby="home-slide-library">
      <div class="home-column-head">
        <h3 id="home-slide-library">Slide library</h3>
      </div>
      <div class="home-library-list" role="listbox" aria-label="Opening slides" aria-orientation="vertical" data-slide-list>
        @foreach($group['items'] as $index => $item)
          @php
            $thumb = $picture($item['image']);
            $fallbackThumb = $picture($item['defaults']['image'] ?? '');
            $restore = old('restore.'.$group['key'].'.'.$item['slot']);
            $useOriginal = is_string($restore) && $restore !== '' && $restore !== '0';
            $thumbSrc = $useOriginal ? $fallbackThumb : ($thumb !== '' ? $thumb : $fallbackThumb);
            $libraryTitle = $display($item['slot'], 'title', $item['title'], $item['defaults']['title']);
            $libraryState = $useOriginal || ! $item['custom'] ? 'Original photograph' : 'Image replaced';
            $selected = $item['slot'] === $activeSlot;
          @endphp
          <button class="home-library-item{{ $selected ? ' is-active' : '' }}" type="button" role="option" data-select-slide="{{ $item['slot'] }}" aria-selected="{{ $selected ? 'true' : 'false' }}" id="home-slide-option-{{ $item['slot'] }}">
            @if($thumbSrc !== '')
              <img src="{{ $thumbSrc }}" alt="" width="72" height="52" data-library-thumb @if($index > 0) loading="lazy" @endif>
            @else
              <span class="home-library-missing" data-library-missing>No photo</span>
            @endif
            <span class="home-library-copy">
              <strong data-library-title>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}. {{ $libraryTitle }}</strong>
              <span data-library-state>{{ $libraryState }}</span>
            </span>
          </button>
        @endforeach
      </div>
      <p class="home-library-foot">Showing all {{ $slideCount }} {{ $slideCount === 1 ? 'slide' : 'slides' }}</p>
    </section>

    <section class="home-column home-editor" aria-labelledby="home-editor-title">
      <div class="home-column-head home-editor-heading">
        <h3 id="home-editor-title" data-editor-title>Edit slide {{ str_pad((string) ($activeIndex + 1), 2, '0', STR_PAD_LEFT) }}</h3>
        <span class="home-current">Current slide</span>
      </div>
      <div class="home-editor-tabs" role="tablist" aria-label="Slide fields" data-editor-tabs>
        <button type="button" role="tab" id="home-editor-tab-content" data-editor-tab="content" aria-selected="{{ $activeTab === 'content' ? 'true' : 'false' }}" aria-controls="home-{{ $activeSlot }}-content" tabindex="{{ $activeTab === 'content' ? '0' : '-1' }}">
          Content
          <span class="home-tab-dot" @unless($tabHasError('content')) hidden @endunless></span>
        </button>
        <button type="button" role="tab" id="home-editor-tab-image" data-editor-tab="image" aria-selected="{{ $activeTab === 'image' ? 'true' : 'false' }}" aria-controls="home-{{ $activeSlot }}-image" tabindex="{{ $activeTab === 'image' ? '0' : '-1' }}">
          Images
          <span class="home-tab-dot" @unless($tabHasError('image')) hidden @endunless></span>
        </button>
      </div>

      @foreach($group['items'] as $item)
        @php
          $defaults = $item['defaults'];
          $selected = $item['slot'] === $activeSlot;
          $currentImage = $picture($item['image']);
          $fallbackImage = $picture($defaults['image'] ?? '');
          $restore = old('restore.'.$group['key'].'.'.$item['slot']);
          $useOriginal = is_string($restore) && $restore !== '' && $restore !== '0';
          $frameSrc = $useOriginal ? $fallbackImage : ($currentImage !== '' ? $currentImage : $fallbackImage);
          $frameAlt = $display($item['slot'], 'alt', $item['alt'], $defaults['alt']);
          $frameState = $useOriginal || ! $item['custom'] ? 'Original photograph' : 'Image replaced';
        @endphp
        <div class="home-slide-fields{{ $selected ? ' is-active' : '' }}" data-slide="{{ $item['slot'] }}">
          <div class="home-editor-tabpanel{{ $activeTab === 'content' ? ' is-active' : '' }}" data-tab="content" id="home-{{ $item['slot'] }}-content" role="tabpanel" aria-labelledby="home-editor-tab-content">
            <div class="home-field">
              <div class="home-field-label">
                <label for="eyebrow-{{ $group['key'] }}-{{ $item['slot'] }}">Eyebrow</label>
                <span>Small label above title</span>
              </div>
              <input class="home-input{{ $errors->has('eyebrow.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="eyebrow-{{ $group['key'] }}-{{ $item['slot'] }}" name="eyebrow[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('eyebrow.'.$group['key'].'.'.$item['slot'], $item['eyebrow']) }}" maxlength="80" placeholder="{{ $defaults['eyebrow'] }}" data-field="eyebrow" @if($errors->has('eyebrow.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              @error('eyebrow.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            <div class="home-field">
              <div class="home-field-label">
                <label for="title-{{ $group['key'] }}-{{ $item['slot'] }}">Title</label>
                <span>Hero heading</span>
              </div>
              <input class="home-input{{ $errors->has('title.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="title-{{ $group['key'] }}-{{ $item['slot'] }}" name="title[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('title.'.$group['key'].'.'.$item['slot'], $item['title']) }}" maxlength="160" placeholder="{{ $defaults['title'] }}" data-field="title" @if($errors->has('title.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              @error('title.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            @if($item['second_line'])
              <div class="home-field">
                <div class="home-field-label">
                  <label for="subtitle-{{ $group['key'] }}-{{ $item['slot'] }}">Second line</label>
                  <span>Optional</span>
                </div>
                <input class="home-input{{ $errors->has('subtitle.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="subtitle-{{ $group['key'] }}-{{ $item['slot'] }}" name="subtitle[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('subtitle.'.$group['key'].'.'.$item['slot'], $item['subtitle']) }}" maxlength="120" placeholder="{{ $defaults['subtitle'] }}" data-field="subtitle" @if($errors->has('subtitle.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
                @error('subtitle.'.$group['key'].'.'.$item['slot'])
                  <p class="home-field-error">{{ $message }}</p>
                @enderror
              </div>
            @endif
            <div class="home-field">
              <div class="home-field-label">
                <label for="body-{{ $group['key'] }}-{{ $item['slot'] }}">Paragraph</label>
                <span>Under the heading</span>
              </div>
              <textarea class="home-input{{ $errors->has('body.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="body-{{ $group['key'] }}-{{ $item['slot'] }}" name="body[{{ $group['key'] }}][{{ $item['slot'] }}]" rows="4" maxlength="500" placeholder="{{ $defaults['text'] }}" data-field="body" @if($errors->has('body.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>{{ old('body.'.$group['key'].'.'.$item['slot'], $item['text']) }}</textarea>
              @error('body.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            <div class="home-field">
              <div class="home-field-label">
                <label for="button-{{ $group['key'] }}-{{ $item['slot'] }}">Button</label>
                <span>Label on the slide</span>
              </div>
              <input class="home-input{{ $errors->has('button_label.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="button-{{ $group['key'] }}-{{ $item['slot'] }}" name="button_label[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('button_label.'.$group['key'].'.'.$item['slot'], $item['label']) }}" maxlength="80" placeholder="{{ $defaults['label'] }}" data-field="button_label" @if($errors->has('button_label.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              @error('button_label.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            <div class="home-field">
              <div class="home-field-label">
                <label for="link-{{ $group['key'] }}-{{ $item['slot'] }}">Page</label>
                <span>Must already exist</span>
              </div>
              <input class="home-input{{ $errors->has('link.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="link-{{ $group['key'] }}-{{ $item['slot'] }}" name="link[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('link.'.$group['key'].'.'.$item['slot'], $item['url']) }}" maxlength="200" placeholder="{{ $defaults['url'] }}" data-field="link" @if($errors->has('link.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              @error('link.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            <div class="home-field">
              <div class="home-field-label">
                <label for="alt-{{ $group['key'] }}-{{ $item['slot'] }}">Description</label>
                <span>Alternative text</span>
              </div>
              <input class="home-input{{ $errors->has('alt.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="alt-{{ $group['key'] }}-{{ $item['slot'] }}" name="alt[{{ $group['key'] }}][{{ $item['slot'] }}]" value="{{ old('alt.'.$group['key'].'.'.$item['slot'], $item['alt']) }}" maxlength="180" placeholder="{{ $defaults['alt'] }}" data-field="alt" @if($errors->has('alt.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              @error('alt.'.$group['key'].'.'.$item['slot'])
                <p class="home-field-error">{{ $message }}</p>
              @enderror
            </div>
            <p class="home-editor-note">The photograph stays on Images. Leave a field blank to keep the wording already on the home page.</p>
          </div>

          <div class="home-editor-tabpanel{{ $activeTab === 'image' ? ' is-active' : '' }}" data-tab="image" id="home-{{ $item['slot'] }}-image" role="tabpanel" aria-labelledby="home-editor-tab-image">
            <div class="home-photo">
              <div class="home-photo-frame">
                @if($frameSrc !== '')
                  <img src="{{ $frameSrc }}" alt="{{ $frameAlt }}" data-photo-preview>
                @else
                  <img alt="" data-photo-preview hidden>
                @endif
                <p class="home-photo-missing" data-photo-missing @if($frameSrc !== '') hidden @endif>No photograph yet</p>
              </div>
              <p class="home-photo-caption" data-photo-caption>{{ $frameState }}</p>
              <div class="home-file">
                <label class="home-file-btn" for="photo-{{ $group['key'] }}-{{ $item['slot'] }}">Replace photograph</label>
                <input class="home-file-input{{ $errors->has('photo.'.$group['key'].'.'.$item['slot']) ? ' is-invalid' : '' }}" id="photo-{{ $group['key'] }}-{{ $item['slot'] }}" name="photo[{{ $group['key'] }}][{{ $item['slot'] }}]" type="file" accept="image/jpeg,image/png,image/webp" data-field="photo" aria-describedby="photo-{{ $group['key'] }}-{{ $item['slot'] }}-error" @if($errors->has('photo.'.$group['key'].'.'.$item['slot'])) aria-invalid="true" @endif>
              </div>
              <p class="home-help">JPG, PNG, or WebP. Maximum 5 MB.</p>
              <p class="home-file-name" data-file-name hidden></p>
              <button class="home-text-btn" type="button" data-clear-photo hidden>Remove selected photograph</button>
              <p class="home-field-error" id="photo-{{ $group['key'] }}-{{ $item['slot'] }}-error" data-file-error @unless($errors->has('photo.'.$group['key'].'.'.$item['slot'])) hidden @endunless>@error('photo.'.$group['key'].'.'.$item['slot']){{ $message }}@enderror</p>
              @if($item['custom'])
                <label class="home-check">
                  <input type="checkbox" name="restore[{{ $group['key'] }}][{{ $item['slot'] }}]" value="1" data-field="restore" @checked(old('restore.'.$group['key'].'.'.$item['slot']))>
                  Use the original photograph
                </label>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </section>

    <section class="home-column home-preview" aria-labelledby="home-preview-title">
      <div class="home-column-head home-preview-head">
        <h3 id="home-preview-title">Live homepage preview</h3>
        <div class="home-preview-tools">
          <button class="home-btn home-btn-quiet" type="button" data-preview-sizes>
            <i class="bi bi-aspect-ratio" aria-hidden="true"></i>
            Preview sizes
          </button>
          <div class="home-mode" role="group" aria-label="Preview size">
            <button type="button" data-preview-mode="desktop" aria-pressed="true"><i class="bi bi-display" aria-hidden="true"></i> Desktop</button>
            <button type="button" data-preview-mode="mobile" aria-pressed="false"><i class="bi bi-phone" aria-hidden="true"></i> Mobile</button>
          </div>
        </div>
      </div>
      <div class="home-preview-frame" data-preview-frame>
        <div class="home-preview-chrome">
          <span class="home-preview-dots" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="home-preview-url">{{ $previewHost }}</span>
        </div>
        <div class="home-preview-nav" aria-hidden="true">
          <strong>Royal Indian Stones</strong>
          <span>Home</span>
          <span>Our Stones</span>
          <span>Blog</span>
          <span>Contact</span>
        </div>
        <div class="home-preview-hero">
          @if($previewSrc !== '')
            <img data-preview="image" src="{{ $previewSrc }}" alt="{{ $previewAlt }}">
          @else
            <img data-preview="image" alt="" hidden>
          @endif
          <div class="home-preview-shade"></div>
          <div class="home-preview-copy">
            <p data-preview="eyebrow">{{ $previewEyebrow }}</p>
            <h4 data-preview="title">{{ $previewTitle }}@if($previewSubtitle !== '')<br>{{ $previewSubtitle }}@endif</h4>
            <p data-preview="body">{{ $previewBody }}</p>
            <div class="home-preview-actions">
              <span class="home-preview-cta"><span data-preview="button">{{ $previewButton }}</span> <span aria-hidden="true">↗</span></span>
              <span class="home-preview-quote">Request a Quote</span>
            </div>
          </div>
        </div>
      </div>
      <div class="home-preview-controls">
        <button type="button" data-preview-prev aria-label="Previous slide">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
          <span data-preview-count>{{ $countLabel }}</span>
        </button>
        <div class="home-pips" data-preview-dots></div>
        <button type="button" data-preview-next>
          Next slide
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
      </div>
      <p class="home-preview-destination">Page <span data-preview="link">{{ $previewLink }}</span></p>
      <p class="home-preview-note">Changes in the form appear in the preview before you save. Preview does not publish to the live website.</p>
    </section>
  </div>
  <dialog class="home-size-dialog" id="home-preview-sizes" data-preview-sizes-dialog aria-labelledby="home-preview-sizes-title">
    <div class="home-size-head">
      <h3 id="home-preview-sizes-title">Preview sizes</h3>
      <button class="home-drawer-close" type="button" data-preview-sizes-close aria-label="Close preview">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
    <div class="home-size-grid">
      <section>
        <p>Desktop</p>
        <div class="home-size-desktop" data-size-slot="desktop"></div>
      </section>
      <section>
        <p>Mobile</p>
        <div class="home-size-mobile" data-size-slot="mobile"></div>
      </section>
    </div>
  </dialog>
  <script type="application/json" id="home-opening-slides">@json(['slides' => $editorSlides, 'errors' => (object) $heroErrors])</script>
</form>
