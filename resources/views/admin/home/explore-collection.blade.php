@php
  use App\Support\HomeContent;

  $section = $group['key'];
  $defaults = HomeContent::sectionDefaults()['explore'];
  $byName = [];
  foreach ($group['fields'] as $field) {
      $byName[$field['name']] = $field;
  }
  $heading = $byName['heading'] ?? ['max' => 160, 'value' => $defaults['heading']];
  $body = $byName['body'] ?? ['max' => 1200, 'value' => $defaults['body']];
  $value = function (string $name) use ($byName, $section): string {
      return old('copy.'.$section.'.'.$name, (string) ($byName[$name]['value'] ?? ''));
  };
  $invalid = function (string $name) use ($errors, $section): bool {
      return $errors->has('copy.'.$section.'.'.$name);
  };
  $cards = $group['items'];
  $cardCount = count($cards);
  $countLabel = $cardCount === 1 ? '1 category card' : $cardCount.' category cards';
  $knownSlots = array_column($cards, 'slot');
  $openCard = '';
  foreach ($errors->keys() as $key) {
      $parts = explode('.', $key);
      if (($parts[1] ?? '') !== $section || ! in_array($parts[0] ?? '', ['alt', 'photo', 'restore'], true)) {
          continue;
      }
      $candidate = $parts[2] ?? '';
      if ($candidate !== '' && in_array($candidate, $knownSlots, true)) {
          $openCard = $candidate;
          break;
      }
  }
  $picture = function (mixed $path): string {
      if (! is_string($path) || $path === '') {
          return '';
      }

      return asset($path);
  };
  $openAttributes = $openSection === $section ? ' data-open="1"' : '';
  if ($openCard !== '') {
      $openAttributes .= ' data-open-slot="'.e($openCard).'"';
  }
@endphp

<form id="home-panel-{{ $section }}" class="home-editor-form home-panel home-card{{ $openSection === $section ? ' is-open' : '' }}" method="post" enctype="multipart/form-data" action="{{ route('admin.home.update', $section) }}" data-home-panel="{{ $section }}"{!! $openAttributes !!} role="tabpanel" aria-labelledby="home-tab-{{ $section }}" autocomplete="off">
  @csrf
  @method('PUT')
  <div class="home-card-head">
    <div class="home-card-title">
      <span class="home-index">03</span>
      <div>
        <h2 id="home-explore-title">Explore collection</h2>
        <p>{{ $group['intro'] }}</p>
      </div>
    </div>
    <div class="home-card-tools">
      <span class="home-order">{{ $countLabel }}</span>
      <button class="home-btn home-btn-quiet" type="button" data-discard disabled>Discard</button>
      <button class="home-btn home-btn-primary" type="submit" data-save>
        <i class="bi bi-check2" aria-hidden="true"></i>
        Save section
      </button>
    </div>
  </div>

  <div class="home-explore-body">
    <section class="home-explore-intro" aria-label="Section introduction">
      <div class="home-field">
        <div class="home-field-label">
          <label for="explore-heading">Heading</label>
        </div>
        <input class="home-input{{ $invalid('heading') ? ' is-invalid' : '' }}" id="explore-heading" name="copy[{{ $section }}][heading]" value="{{ $value('heading') }}" maxlength="{{ $heading['max'] }}" placeholder="{{ $defaults['heading'] }}" data-field="heading" @if($invalid('heading')) aria-invalid="true" aria-describedby="explore-heading-error" @endif>
        @error('copy.'.$section.'.heading')
          <p class="home-field-error" id="explore-heading-error">{{ $message }}</p>
        @enderror
      </div>
      <div class="home-field">
        <div class="home-field-label">
          <label for="explore-body">Paragraph</label>
        </div>
        <textarea class="home-input home-explore-paragraph{{ $invalid('body') ? ' is-invalid' : '' }}" id="explore-body" name="copy[{{ $section }}][body]" rows="2" maxlength="{{ $body['max'] }}" placeholder="{{ $defaults['body'] }}" data-field="body" @if($invalid('body')) aria-invalid="true" aria-describedby="explore-body-error" @endif>{{ $value('body') }}</textarea>
        @error('copy.'.$section.'.body')
          <p class="home-field-error" id="explore-body-error">{{ $message }}</p>
        @enderror
      </div>
    </section>

    <section class="home-explore-gallery" aria-labelledby="home-explore-cards-title">
      <div class="home-explore-gallery-head">
        <h3 id="home-explore-cards-title">Category cards <span class="home-total">{{ $cardCount }} total</span></h3>
      </div>
      @if($cards === [])
        <p class="home-editor-empty">Nothing to show in this section yet.</p>
      @else
        <div class="home-explore-grid">
          @foreach($cards as $item)
            @php
              $fallback = is_string($item['fallback'] ?? null) ? $item['fallback'] : '';
              $restore = old('restore.'.$section.'.'.$item['slot']);
              $useOriginal = (bool) $item['custom'] && is_string($restore) && $restore !== '' && $restore !== '0';
              $cardSrc = $useOriginal ? $picture($fallback) : $picture($item['image']);
              if ($cardSrc === '') {
                  $cardSrc = $picture($fallback);
              }
              $imageLabel = $cardSrc === ''
                  ? 'No photograph'
                  : ($item['custom'] && ! $useOriginal ? 'Homepage photograph' : 'Catalogue photograph');
            @endphp
            <article class="home-explore-card" data-explore-card="{{ $item['slot'] }}" aria-labelledby="explore-card-{{ $item['slot'] }}">
              @if($cardSrc !== '')
                <img class="home-explore-thumb" src="{{ $cardSrc }}" alt="" width="640" height="480" data-card-thumb>
                <div class="home-explore-missing" data-card-missing hidden>No photograph yet</div>
              @else
                <img class="home-explore-thumb" alt="" width="640" height="480" data-card-thumb hidden>
                <div class="home-explore-missing" data-card-missing>No photograph yet</div>
              @endif
              <div class="home-explore-card-body">
                <div>
                  <h3 id="explore-card-{{ $item['slot'] }}">{{ $item['heading'] }}</h3>
                  <p class="home-explore-meta">
                    <span data-card-state>{{ $imageLabel }}</span>
                    <span aria-hidden="true"> · </span>
                    <span>{{ $item['detail'] }}</span>
                  </p>
                </div>
                <button class="home-btn home-btn-quiet home-explore-edit" type="button" data-edit-card="{{ $item['slot'] }}" aria-haspopup="dialog" aria-controls="home-explore-drawer" aria-expanded="false" aria-label="Edit {{ $item['heading'] }}">
                  <i class="bi bi-pencil" aria-hidden="true"></i>
                  Edit
                </button>
              </div>
            </article>
          @endforeach
        </div>
      @endif
      <div class="home-explore-notes">
        <p>Each card keeps its catalogue name and link. Open Edit to update the homepage description or replace its photograph.</p>
        <p>Images shown on the home page only.</p>
      </div>
    </section>

    <div class="home-explore-store" data-explore-store>
      @foreach($cards as $item)
        @php
          $fallback = is_string($item['fallback'] ?? null) ? $item['fallback'] : '';
          $restore = old('restore.'.$section.'.'.$item['slot']);
          $useOriginal = (bool) $item['custom'] && is_string($restore) && $restore !== '' && $restore !== '0';
          $frameSrc = $useOriginal ? $picture($fallback) : $picture($item['image']);
          if ($frameSrc === '') {
              $frameSrc = $picture($fallback);
          }
          $frameState = $frameSrc === ''
              ? 'No photograph'
              : ($item['custom'] && ! $useOriginal ? 'Homepage photograph' : 'Catalogue photograph');
          $photoInvalid = $errors->has('photo.'.$section.'.'.$item['slot']);
          $altInvalid = $errors->has('alt.'.$section.'.'.$item['slot']);
          $altValue = old('alt.'.$section.'.'.$item['slot'], $item['alt']);
          $mustSubmit = $photoInvalid || $altInvalid || $useOriginal;
        @endphp
        <div class="home-explore-fields" id="explore-editor-{{ $item['slot'] }}" data-explore-slot="{{ $item['slot'] }}" data-name="{{ $item['heading'] }}" data-url="{{ $item['detail'] }}" data-image="{{ $picture($item['image']) }}" data-fallback="{{ $picture($fallback) }}" data-custom="{{ $item['custom'] ? '1' : '0' }}" data-must-submit="{{ $mustSubmit ? '1' : '0' }}">
          <h3 class="home-explore-fields-title">{{ $item['heading'] }}</h3>
          <div class="home-explore-destination">
            <span>Catalogue destination</span>
            <a href="{{ url($item['detail']) }}" target="_blank" rel="noopener">{{ $item['detail'] }}</a>
          </div>
          <div class="home-photo">
            <p class="home-photo-label" id="photo-label-{{ $section }}-{{ $item['slot'] }}">Current homepage image</p>
            <div class="home-photo-frame">
              @if($frameSrc !== '')
                <img src="{{ $frameSrc }}" alt="{{ $altValue }}" data-photo-preview>
              @else
                <img alt="" data-photo-preview hidden>
              @endif
              <p class="home-photo-missing" data-photo-missing @if($frameSrc !== '') hidden @endif>No photograph yet</p>
            </div>
            <p class="home-photo-caption" data-photo-caption>{{ $frameState }}</p>
            <div class="home-file">
              <label class="home-file-btn" for="photo-{{ $section }}-{{ $item['slot'] }}">Replace photograph</label>
              <input class="home-file-input{{ $photoInvalid ? ' is-invalid' : '' }}" id="photo-{{ $section }}-{{ $item['slot'] }}" name="photo[{{ $section }}][{{ $item['slot'] }}]" type="file" accept="image/jpeg,image/png,image/webp" data-field="photo" aria-describedby="photo-{{ $section }}-{{ $item['slot'] }}-help photo-{{ $section }}-{{ $item['slot'] }}-error" @if($photoInvalid) aria-invalid="true" @endif>
            </div>
            <p class="home-help" id="photo-{{ $section }}-{{ $item['slot'] }}-help">JPG, PNG, or WebP. Maximum 5 MB. The catalogue photograph is left unchanged.</p>
            <p class="home-file-name" data-file-name hidden></p>
            <button class="home-text-btn" type="button" data-clear-photo hidden>Remove selected photograph</button>
            <p class="home-field-error" id="photo-{{ $section }}-{{ $item['slot'] }}-error" data-file-error @unless($photoInvalid) hidden @endunless>@error('photo.'.$section.'.'.$item['slot']){{ $message }}@enderror</p>
            @if($item['custom'])
              <label class="home-check">
                <input type="checkbox" name="restore[{{ $section }}][{{ $item['slot'] }}]" value="1" data-field="restore" @checked($useOriginal)>
                Use the original photograph
              </label>
            @endif
          </div>
          <div class="home-field">
            <div class="home-field-label">
              <label for="alt-{{ $section }}-{{ $item['slot'] }}">Homepage description</label>
              <span>Read with the photograph</span>
            </div>
            <textarea class="home-input home-explore-description{{ $altInvalid ? ' is-invalid' : '' }}" id="alt-{{ $section }}-{{ $item['slot'] }}" name="alt[{{ $section }}][{{ $item['slot'] }}]" rows="3" maxlength="180" data-field="alt" @if($altInvalid) aria-invalid="true" aria-describedby="alt-{{ $section }}-{{ $item['slot'] }}-error" @endif>{{ $altValue }}</textarea>
            @error('alt.'.$section.'.'.$item['slot'])
              <p class="home-field-error" id="alt-{{ $section }}-{{ $item['slot'] }}-error">{{ $message }}</p>
            @enderror
            <p class="home-help">Leave this blank to keep the current wording. The category name and catalogue page stay as they are.</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <dialog class="home-drawer" id="home-explore-drawer" data-explore-drawer aria-labelledby="home-explore-drawer-title" aria-describedby="home-explore-drawer-note">
    <div class="home-drawer-head">
      <div>
        <p class="home-kicker">Category</p>
        <h3 id="home-explore-drawer-title" data-drawer-title tabindex="-1">Edit category</h3>
      </div>
      <button class="home-drawer-close" type="button" data-drawer-close aria-label="Close">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
    <div class="home-drawer-body" data-drawer-mount></div>
    <div class="home-drawer-foot">
      <p id="home-explore-drawer-note">A new photograph is stored for the home page only. The catalogue image stays in place.</p>
      <div class="home-drawer-actions">
        <button class="home-btn home-btn-quiet" type="button" data-drawer-cancel>Cancel</button>
        <button class="home-btn home-btn-primary" type="submit" form="home-panel-{{ $section }}" data-drawer-save>
          <i class="bi bi-check2" aria-hidden="true"></i>
          Save
        </button>
      </div>
    </div>
  </dialog>
</form>
