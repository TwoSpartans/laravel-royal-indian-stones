@extends('layouts.site')

@section('content')
<main id="main">
  <section class="e-catalogue">
    <div class="container">
      <p class="eyebrow">Royal Indian Stones</p>
      <h1>E-catalogue</h1>
      <p class="e-catalogue-intro">Discover the full range of natural stones we quarry, cut and export from India. Each catalogue brings together our latest collections — complete with finishes, sizes and applications — so architects, landscapers and importers can quickly find the right material. Preview any catalogue right in your browser or download the PDF, then open a material to explore the individual stones.</p>

      @if($catalogues->isNotEmpty())
        <section class="e-catalogue-block" aria-labelledby="e-catalogue-books">
          <div class="e-catalogue-section-head">
            <h2 id="e-catalogue-books">Catalogues</h2>
          </div>
          <div class="e-book-grid">
            @foreach($catalogues as $catalogue)
              <article class="e-book">
                @if($catalogue->cover_path)
                  <x-picture src="{{ $catalogue->cover_path }}" :alt="$catalogue->title" width="800" height="600" loading="lazy" sizes="(max-width: 767px) calc(100vw - 40px), (max-width: 1199px) calc(50vw - 36px), 380px" />
                @else
                  <span class="e-book-placeholder" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>
                @endif
                <div class="e-book-body">
                  <h3>{{ $catalogue->title }}</h3>
                  @if(filled($catalogue->description))
                    <p class="e-book-summary">{{ $catalogue->description }}</p>
                  @endif
                  <div class="button-row e-book-actions">
                    <a class="ris-button ris-button-outline" href="{{ route('e-catalogue.preview', ['catalogue' => $catalogue->slug]) }}" data-catalogue-preview data-preview-title="{{ $catalogue->title }}">Preview</a>
                    <a class="ris-button ris-button-outline" href="{{ asset($catalogue->pdf_path) }}" download="{{ $catalogue->pdf_original_name ?: $catalogue->slug.'.pdf' }}">Download PDF</a>
                  </div>
                </div>
              </article>
            @endforeach
          </div>
        </section>
      @endif

      <section class="e-catalogue-block" aria-labelledby="e-catalogue-materials">
        <div class="e-catalogue-section-head">
          <h2 id="e-catalogue-materials">Materials</h2>
          <p>Open a collection to see the stones in that material.</p>
        </div>
        @if($categories->isEmpty())
          <p class="empty-state">The catalogue is being updated. <a href="{{ route('contact') }}">Contact us</a> and we will send the current range.</p>
        @else
          <div class="e-material-grid">
            @foreach($categories as $category)
              @php
                $summary = $category->catalogueSummary();
                $count = (int) $category->published_products_count;
              @endphp
              <article class="e-material">
                @if($category->image)
                  <x-picture src="{{ $category->image }}" :alt="$category->name" width="800" height="600" loading="lazy" sizes="(max-width: 767px) calc(100vw - 40px), (max-width: 1199px) calc(50vw - 36px), 380px" />
                @else
                  <span class="e-catalogue-placeholder" aria-hidden="true"></span>
                @endif
                <div class="e-material-body">
                  <h3>{{ $category->name }}</h3>
                  @if($summary !== '')
                    <p class="e-material-summary">{{ $summary }}</p>
                  @endif
                  <p class="e-catalogue-count">{{ $count }} {{ $count === 1 ? 'product' : 'products' }}</p>
                  <a class="ris-button ris-button-outline" href="{{ url('/'.$category->slug) }}">View collection</a>
                </div>
              </article>
            @endforeach
          </div>
        @endif
      </section>
    </div>
  </section>
</main>
@if($catalogues->isNotEmpty())
  <div class="modal fade catalogue-preview-modal" id="cataloguePreviewModal" tabindex="-1" aria-labelledby="cataloguePreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-md-down">
      <div class="modal-content">
        <div class="modal-header">
          <div class="modal-heading">
            <p class="modal-kicker" data-preview-kicker>E-catalogue</p>
            <h2 class="modal-title" id="cataloguePreviewTitle" data-preview-title>Catalogue</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" data-preview-body>
          <p class="catalogue-preview-status">Loading preview…</p>
        </div>
      </div>
    </div>
  </div>
@endif
@endsection

@push('scripts')
<script defer>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('cataloguePreviewModal');
  const body = modalEl?.querySelector('[data-preview-body]');
  if (!modalEl || !body || !window.bootstrap) {
    return;
  }

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const title = modalEl.querySelector('[data-preview-title]');
  const kicker = modalEl.querySelector('[data-preview-kicker]');
  let requestId = 0;

  const showStatus = (message, linkHref = '') => {
    body.replaceChildren();
    const paragraph = document.createElement('p');
    paragraph.className = 'catalogue-preview-status';
    paragraph.textContent = message;
    if (linkHref !== '') {
      paragraph.append(' ');
      const anchor = document.createElement('a');
      anchor.href = linkHref;
      anchor.textContent = 'Open the preview page';
      paragraph.append(anchor);
    }
    body.append(paragraph);
  };

  document.querySelectorAll('[data-catalogue-preview]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      event.preventDefault();
      const pageUrl = link.href;
      const current = ++requestId;
      if (title) {
        title.textContent = link.dataset.previewTitle || 'Catalogue';
      }
      if (kicker) {
        kicker.textContent = 'E-catalogue';
      }
      showStatus('Loading preview…');
      modal.show();

      const embedUrl = new URL(pageUrl, window.location.origin);
      embedUrl.searchParams.set('embed', '1');
      fetch(embedUrl.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => {
          if (!response.ok) {
            throw new Error('preview');
          }

          return response.text();
        })
        .then((html) => {
          if (current !== requestId) {
            return;
          }
          body.innerHTML = html;
        })
        .catch(() => {
          if (current !== requestId) {
            return;
          }
          showStatus('The preview could not be loaded.', pageUrl);
        });
    });
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    requestId += 1;
    body.replaceChildren();
  });
});
</script>
@endpush
