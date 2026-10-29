@php
  use App\Support\HomeContent;

  $section = $group['key'];
  $defaults = HomeContent::sectionDefaults()['stones'];
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
  $knownSlots = array_column($cards, 'slot');
  $openCard = '';
  foreach ($errors->keys() as $key) {
      $parts = explode('.', $key);
      if (($parts[1] ?? '') !== $section || ! in_array($parts[0] ?? '', ['title', 'link', 'alt', 'photo', 'restore'], true)) {
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
      <span class="home-index">04</span>
      <div>
        <h2 id="home-stones-title">Natural Indian Stones</h2>
        <p>{{ $group['intro'] }}</p>
      </div>
    </div>
    <div class="home-card-tools">
      <span class="home-order">{{ $cardCount }} {{ $cardCount === 1 ? 'item' : 'items' }} · Link validation</span>
      <button class="home-btn home-btn-quiet" type="button" data-discard disabled>Discard</button>
      <button class="home-btn home-btn-primary" type="submit" data-save>
        <i class="bi bi-check2" aria-hidden="true"></i>
        Save section
      </button>
    </div>
  </div>

  <div class="home-stones-body">
    <section class="home-stones-intro" aria-label="Section introduction">
      <div class="home-field">
        <div class="home-field-label">
          <label for="stones-heading">Heading</label>
        </div>
        <input class="home-input{{ $invalid('heading') ? ' is-invalid' : '' }}" id="stones-heading" name="copy[{{ $section }}][heading]" value="{{ $value('heading') }}" maxlength="{{ $heading['max'] }}" placeholder="{{ $defaults['heading'] }}" data-field="heading" @if($invalid('heading')) aria-invalid="true" aria-describedby="stones-heading-error" @endif>
        @error('copy.'.$section.'.heading')
          <p class="home-field-error" id="stones-heading-error">{{ $message }}</p>
        @enderror
      </div>
      <div class="home-field">
        <div class="home-field-label">
          <label for="stones-body">Paragraph</label>
          <span>Section introduction</span>
        </div>
        <textarea class="home-input home-stones-paragraph{{ $invalid('body') ? ' is-invalid' : '' }}" id="stones-body" name="copy[{{ $section }}][body]" rows="4" maxlength="{{ $body['max'] }}" placeholder="{{ $defaults['body'] }}" data-field="body" @if($invalid('body')) aria-invalid="true" aria-describedby="stones-body-error" @endif>{{ $value('body') }}</textarea>
        @error('copy.'.$section.'.body')
          <p class="home-field-error" id="stones-body-error">{{ $message }}</p>
        @enderror
      </div>
    </section>

    <div class="home-stones-layout">
      <section class="home-stones-library" aria-labelledby="home-stones-library-title">
        <div class="home-stones-library-head">
          <h3 id="home-stones-library-title">Stone application cards <span class="home-total">{{ $cardCount }} total</span></h3>
          <div class="home-stone-search">
            <label class="visually-hidden" for="stones-search">Search by application name or page</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input class="home-input" id="stones-search" type="search" placeholder="Search by name or page" data-stone-search autocomplete="off">
          </div>
        </div>
        <div class="home-stone-table-wrap">
          <table class="home-stone-table">
            <thead>
              <tr>
                <th scope="col">Image</th>
                <th scope="col">Stone application</th>
                <th scope="col">Destination page</th>
                <th scope="col">Photograph</th>
                <th scope="col"><span class="visually-hidden">Action</span></th>
              </tr>
            </thead>
            <tbody>
              @foreach($cards as $index => $item)
                @php
                  $thumb = $picture($item['image']);
                  $photoState = $item['custom'] ? 'Homepage photograph' : ($thumb !== '' ? 'Original photograph' : 'No photograph');
                @endphp
                <tr data-stone-row="{{ $item['slot'] }}" data-stone-index="{{ $index }}">
                  <td>
                    @if($thumb !== '')
                      <img class="home-stone-thumb" src="{{ $thumb }}" alt="" width="96" height="72" data-stone-thumb>
                    @else
                      <img class="home-stone-thumb" alt="" width="96" height="72" data-stone-thumb hidden>
                    @endif
                  </td>
                  <td data-stone-name>{{ $item['heading'] }}</td>
                  <td class="home-stone-page-cell" data-stone-destination>{{ $item['url'] }}</td>
                  <td data-stone-photo>{{ $photoState }}</td>
                  <td>
                    <button class="home-btn home-btn-quiet home-stone-edit" type="button" data-stone-edit="{{ $item['slot'] }}" aria-label="Edit {{ $item['heading'] }}">
                      <i class="bi bi-pencil" aria-hidden="true"></i>
                      Edit
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="home-stone-empty" data-stone-empty hidden>No applications match that search.</p>
        <div class="home-stone-pager">
          <p data-stone-range>Showing {{ $cardCount === 0 ? '0' : '1-'.min(6, $cardCount) }} of {{ $cardCount }} entries</p>
          <div class="home-stone-pager-controls">
            <button class="home-btn home-btn-quiet" type="button" data-stone-prev disabled>Previous</button>
            <div class="home-stone-pages" data-stone-pages role="group" aria-label="Stone application pages"></div>
            <button class="home-btn home-btn-quiet" type="button" data-stone-next @if($cardCount <= 6) disabled @endif>Next</button>
          </div>
        </div>
      </section>

      <section class="home-stones-editor" aria-labelledby="home-stones-editor-title">
        <div class="home-stones-editor-head">
          <h3 id="home-stones-editor-title">Edit selected card</h3>
          <span data-stone-position>{{ $cardCount === 0 ? '00 / 00' : '01 / '.str_pad((string) $cardCount, 2, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div data-stone-mount></div>
        <p class="home-field-note">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          <span>The destination page must already exist before assigning this card.</span>
        </p>
      </section>
    </div>

    <div class="home-stone-store" data-stone-store>
      @foreach($cards as $item)
        @php
          $frameSrc = $picture($item['image']);
          $fallbackSrc = $picture($item['fallback']);
          $titleValue = old('title.'.$section.'.'.$item['slot'], $item['name']);
          $linkValue = old('link.'.$section.'.'.$item['slot'], $item['url']);
          $altValue = old('alt.'.$section.'.'.$item['slot'], $item['alt']);
          $titleInvalid = $errors->has('title.'.$section.'.'.$item['slot']);
          $linkInvalid = $errors->has('link.'.$section.'.'.$item['slot']);
          $altInvalid = $errors->has('alt.'.$section.'.'.$item['slot']);
          $photoInvalid = $errors->has('photo.'.$section.'.'.$item['slot']);
          $useOriginal = old('restore.'.$section.'.'.$item['slot']);
          $mustSubmit = $titleInvalid || $linkInvalid || $altInvalid || $photoInvalid || $errors->has('restore.'.$section.'.'.$item['slot']) || $useOriginal;
          $frameState = $item['custom'] ? 'Homepage photograph' : ($frameSrc !== '' ? 'Original photograph' : 'No photograph');
        @endphp
        <div class="home-stone-fields" data-stone-slot="{{ $item['slot'] }}" data-default-name="{{ $item['default_name'] }}" data-default-url="{{ $item['default_url'] }}" data-image="{{ $frameSrc }}" data-fallback="{{ $fallbackSrc }}" data-custom="{{ $item['custom'] ? '1' : '0' }}" data-must-submit="{{ $mustSubmit ? '1' : '0' }}">
          <div class="home-photo">
            <p class="home-photo-label">Current homepage image</p>
            <div class="home-stone-frame">
              @if($frameSrc !== '')
                <img class="home-stone-preview" src="{{ $frameSrc }}" alt="{{ $altValue }}" data-photo-preview>
              @else
                <img class="home-stone-preview" alt="" data-photo-preview hidden>
              @endif
              <p class="home-photo-missing" data-photo-missing @if($frameSrc !== '') hidden @endif>No photograph yet</p>
            </div>
            <p class="home-photo-caption" data-photo-caption>{{ $frameState }}</p>
            <div class="home-file">
              <label class="home-file-btn" for="photo-{{ $section }}-{{ $item['slot'] }}">Replace photograph</label>
              <input class="home-file-input{{ $photoInvalid ? ' is-invalid' : '' }}" id="photo-{{ $section }}-{{ $item['slot'] }}" name="photo[{{ $section }}][{{ $item['slot'] }}]" type="file" accept="image/jpeg,image/png,image/webp" data-field="photo" aria-describedby="photo-{{ $section }}-{{ $item['slot'] }}-help photo-{{ $section }}-{{ $item['slot'] }}-error" @if($photoInvalid) aria-invalid="true" @endif>
            </div>
            <p class="home-help" id="photo-{{ $section }}-{{ $item['slot'] }}-help">JPG, PNG, or WebP. Maximum 5 MB. The original photograph stays in place.</p>
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
              <label for="title-{{ $section }}-{{ $item['slot'] }}">Name</label>
            </div>
            <input class="home-input{{ $titleInvalid ? ' is-invalid' : '' }}" id="title-{{ $section }}-{{ $item['slot'] }}" name="title[{{ $section }}][{{ $item['slot'] }}]" value="{{ $titleValue }}" maxlength="160" data-field="title" @if($titleInvalid) aria-invalid="true" aria-describedby="title-{{ $section }}-{{ $item['slot'] }}-error" @endif>
            @error('title.'.$section.'.'.$item['slot'])
              <p class="home-field-error" id="title-{{ $section }}-{{ $item['slot'] }}-error">{{ $message }}</p>
            @enderror
          </div>
          <div class="home-field">
            <div class="home-field-label">
              <label for="link-{{ $section }}-{{ $item['slot'] }}">Page</label>
            </div>
            <input class="home-input{{ $linkInvalid ? ' is-invalid' : '' }}" id="link-{{ $section }}-{{ $item['slot'] }}" name="link[{{ $section }}][{{ $item['slot'] }}]" value="{{ $linkValue }}" maxlength="200" data-field="link" @if($linkInvalid) aria-invalid="true" aria-describedby="link-{{ $section }}-{{ $item['slot'] }}-error" @endif>
            @error('link.'.$section.'.'.$item['slot'])
              <p class="home-field-error" id="link-{{ $section }}-{{ $item['slot'] }}-error">{{ $message }}</p>
            @enderror
          </div>
          <div class="home-field">
            <div class="home-field-label">
              <label for="alt-{{ $section }}-{{ $item['slot'] }}">Description</label>
              <span>Read with the photograph</span>
            </div>
            <input class="home-input{{ $altInvalid ? ' is-invalid' : '' }}" id="alt-{{ $section }}-{{ $item['slot'] }}" name="alt[{{ $section }}][{{ $item['slot'] }}]" value="{{ $altValue }}" maxlength="180" data-field="alt" @if($altInvalid) aria-invalid="true" aria-describedby="alt-{{ $section }}-{{ $item['slot'] }}-error" @endif>
            @error('alt.'.$section.'.'.$item['slot'])
              <p class="home-field-error" id="alt-{{ $section }}-{{ $item['slot'] }}-error">{{ $message }}</p>
            @enderror
          </div>
        </div>
      @endforeach
    </div>
  </div>
</form>
