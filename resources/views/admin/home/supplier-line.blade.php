@php
  use App\Support\HomeContent;

  $defaults = HomeContent::sectionDefaults()['supplier'];
  $byName = [];
  foreach ($group['fields'] as $field) {
      $byName[$field['name']] = $field;
  }
  $shown = function (string $name) use ($byName, $defaults): string {
      $saved = (string) ($byName[$name]['value'] ?? $defaults[$name] ?? '');
      $submitted = old('copy.supplier.'.$name);
      if (! is_string($submitted)) {
          return $saved;
      }
      $submitted = trim($submitted);

      return $submitted === '' ? (string) ($defaults[$name] ?? '') : $submitted;
  };
  $value = function (string $name) use ($byName): string {
      return old('copy.supplier.'.$name, (string) ($byName[$name]['value'] ?? ''));
  };
  $invalid = function (string $name) use ($errors): bool {
      return $errors->has('copy.supplier.'.$name);
  };
  $heading = $byName['heading'] ?? ['max' => 160, 'value' => $defaults['heading']];
  $body = $byName['body'] ?? ['max' => 1200, 'value' => $defaults['body']];
  $supplierEditor = [
      'saved' => [
          'heading' => (string) ($heading['value'] ?? ''),
          'body' => (string) ($body['value'] ?? ''),
      ],
      'defaults' => [
          'heading' => $defaults['heading'],
          'body' => $defaults['body'],
      ],
  ];
@endphp

<form id="home-panel-supplier" class="home-editor-form home-panel home-card{{ $openSection === 'supplier' ? ' is-open' : '' }}" method="post" action="{{ route('admin.home.update', 'supplier') }}" data-home-panel="supplier"@if($openSection === 'supplier') data-open="1"@endif role="tabpanel" aria-labelledby="home-tab-supplier" autocomplete="off">
  @csrf
  @method('PUT')
  <div class="home-card-head">
    <div class="home-card-title">
      <span class="home-index">02</span>
      <div>
        <h2 id="home-supplier-title">Supplier line</h2>
        <p>A focused text editor with a real-time public-facing section preview.</p>
      </div>
    </div>
    <div class="home-card-tools">
      <span class="home-order">Text section</span>
      <button class="home-btn home-btn-quiet" type="button" data-discard disabled>Discard</button>
      <button class="home-btn home-btn-primary" type="submit" data-save>
        <i class="bi bi-check2" aria-hidden="true"></i>
        Save section
      </button>
    </div>
  </div>

  <div class="home-supplier-grid">
    <section class="home-column" aria-labelledby="home-supplier-content">
      <div class="home-column-head">
        <h3 id="home-supplier-content">Section content</h3>
        <span class="home-current">Copy</span>
      </div>
      <div class="home-supplier-fields">
        <div class="home-field">
          <div class="home-field-label">
            <label for="supplier-heading">Heading</label>
          </div>
          <input class="home-input{{ $invalid('heading') ? ' is-invalid' : '' }}" id="supplier-heading" name="copy[supplier][heading]" value="{{ $value('heading') }}" maxlength="{{ $heading['max'] }}" placeholder="{{ $defaults['heading'] }}" data-field="heading" @if($invalid('heading')) aria-invalid="true" @endif>
          @error('copy.supplier.heading')
            <p class="home-field-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="home-field">
          <div class="home-field-label">
            <label for="supplier-body">Paragraph</label>
            <span>Description visible below opening slides</span>
          </div>
          <textarea class="home-input home-supplier-paragraph{{ $invalid('body') ? ' is-invalid' : '' }}" id="supplier-body" name="copy[supplier][body]" rows="8" maxlength="{{ $body['max'] }}" placeholder="{{ $defaults['body'] }}" data-field="body" @if($invalid('body')) aria-invalid="true" @endif>{{ $value('body') }}</textarea>
          @error('copy.supplier.body')
            <p class="home-field-error">{{ $message }}</p>
          @enderror
        </div>
        <p class="home-field-note">
          <i class="bi bi-info-circle" aria-hidden="true"></i>
          <span>The heading and paragraph stay grouped here. The live preview shows the spacing and typography visitors see. Leave a field blank to keep the wording already on the home page.</span>
        </p>
      </div>
    </section>

    <section class="home-column home-preview" aria-labelledby="home-supplier-preview-title">
      <div class="home-column-head home-preview-head">
        <h3 id="home-supplier-preview-title">Live section preview</h3>
        <div class="home-mode" role="group" aria-label="Preview size">
          <button type="button" data-preview-mode="desktop" aria-pressed="true"><i class="bi bi-display" aria-hidden="true"></i> Desktop</button>
          <button type="button" data-preview-mode="mobile" aria-pressed="false"><i class="bi bi-phone" aria-hidden="true"></i> Mobile</button>
        </div>
      </div>
      <div class="home-preview-frame" data-preview-frame>
        <div class="home-supplier-stage">
          <div class="home-supplier-band">
            <div class="home-supplier-copy">
              <h4 data-supplier-preview="heading">{{ $shown('heading') }}</h4>
              <p data-supplier-preview="body">{{ $shown('body') }}</p>
            </div>
          </div>
        </div>
      </div>
      <p class="home-preview-note">This preview matches the supplier band on the home page. This section has no photograph.</p>
    </section>
  </div>
  <script type="application/json" id="home-supplier-line">@json($supplierEditor)</script>
</form>
