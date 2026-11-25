@extends('layouts.site')

@section('content')
@php
    $storyWord = $resultTotal === 1 ? 'story' : 'stories';
    $foundLabel = $resultTotal.' '.$storyWord.' found';
@endphp
<main id="main">
  <section id="breadcrumbs" class="breadcrumbs">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center">
        <h2>Blogs</h2>
        <ol>
          <li><a href="{{ url('/') }}">Home</a></li>
          @if($topic || $search !== '')
            <li><a href="{{ url('/blog') }}">Blogs</a></li>
            <li>{{ $topic->name ?? 'Search' }}</li>
          @else
            <li>Blogs</li>
          @endif
        </ol>
      </div>
    </div>
  </section>

  <div class="journal">
    @if($resultTotal === 0 && $search === '' && ! $topic)
      <section class="journal-browse" aria-labelledby="journal-empty-title">
        <div class="container">
          <div class="journal-empty">
            <h1 id="journal-empty-title">Stories are on the way</h1>
            <p>There are no published stories yet. The workshop and the stone collection are open while the journal grows.</p>
            <div class="journal-empty-links">
              <a href="{{ url('/products') }}">Browse the stones</a>
              <a href="{{ url('/aboutus') }}">About the workshop</a>
            </div>
          </div>
        </div>
      </section>
    @else
      <h1 class="visually-hidden">Blogs</h1>

      @if($featured)
        <article class="journal-feature">
          <a class="journal-feature-link" href="{{ url('/'.$featured->slug) }}">
            <div class="journal-feature-media">
              @if($featureImage)
                <x-picture src="{{ $featureImage }}" alt="{{ $featured->featured_image_alt ?: $featured->title }}" loading="eager" fetchpriority="high" decoding="sync" sizes="(max-width: 860px) calc(100vw - 2rem), 58vw" />
              @endif
            </div>
            <div class="journal-feature-copy">
              <p class="journal-feature-kicker">Featured story @if($featured->postCategory)<span aria-hidden="true"> / </span>{{ $featured->postCategory->name }}@endif</p>
              <h2>{{ $featured->title }}</h2>
              @if($featured->cardExcerpt() !== '')
                <p class="journal-feature-summary">{{ $featured->cardExcerpt() }}</p>
              @endif
              <span class="journal-button">Read the story<span class="journal-arrow" aria-hidden="true">↗</span></span>
            </div>
          </a>
        </article>
      @endif

      <section class="journal-browse" id="journal-stories" aria-labelledby="journal-browse-title">
        <div class="container">
          <div class="journal-browse-bar">
            <nav class="journal-pills" aria-label="Topics">
              <a href="{{ url('/blog') }}" @if(! $topic && $search === '') aria-current="page" @endif>All stories</a>
              @foreach($topics as $item)
                <a href="{{ url('/blog').'?'.http_build_query(['category' => $item->slug]) }}" @if($topic && $topic->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
              @endforeach
            </nav>
            <form class="journal-search" method="get" action="{{ url('/blog') }}" role="search">
              @if($topic)
                <input type="hidden" name="category" value="{{ $topic->slug }}">
              @endif
              <label class="visually-hidden" for="journal-search">Search stories</label>
              <div class="journal-search-field">
                <img src="{{ asset('assets/img/journal/design/search.svg') }}" alt="" width="18" height="18">
                <input id="journal-search" type="search" name="q" value="{{ $search }}" placeholder="Search stories" maxlength="100" enterkeyhint="search">
                <button type="submit">Search</button>
              </div>
            </form>
          </div>

          @if($search !== '' && $resultTotal > 0)
            <p class="journal-count">{{ $foundLabel }}</p>
          @elseif($search !== '')
            <div class="journal-empty">
              <h2 id="journal-browse-title">No stories match “{{ $search }}”</h2>
              <div class="journal-empty-links">
                <a href="{{ url('/blog') }}">All stories</a>
              </div>
            </div>
          @elseif($topic && $resultTotal === 0)
            <div class="journal-empty">
              <h2 id="journal-browse-title">No stories in {{ $topic->name }} yet</h2>
              <div class="journal-empty-links">
                <a href="{{ url('/blog') }}">All stories</a>
              </div>
            </div>
          @endif

          @if($stories && $stories->isNotEmpty())
            <h2 id="journal-browse-title" class="visually-hidden">All stories</h2>
            <div class="journal-card-grid">
              @foreach($stories as $post)
                <x-journal-card :post="$post" />
              @endforeach
            </div>
            @if($stories->lastPage() > 1)
              <nav class="journal-pager" aria-label="Stories pagination">
                @if($stories->previousPageUrl())
                  <a href="{{ $stories->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                @foreach($stories->getUrlRange(1, $stories->lastPage()) as $pageNumber => $url)
                  @if($pageNumber === $stories->currentPage())
                    <span aria-current="page">{{ $pageNumber }}</span>
                  @else
                    <a href="{{ $url }}" aria-label="Page {{ $pageNumber }}">{{ $pageNumber }}</a>
                  @endif
                @endforeach
                @if($stories->nextPageUrl())
                  <a class="journal-pager-next" href="{{ $stories->nextPageUrl() }}" rel="next">Next <span class="journal-arrow" aria-hidden="true">→</span></a>
                @endif
              </nav>
            @endif
          @endif
        </div>
      </section>
    @endif
  </div>
</main>
@endsection

@push('head')
<link href="{{ asset('assets/css/journal.css') }}?v=20" rel="stylesheet">
@if($previousPageUrl)
<link rel="prev" href="{{ $previousPageUrl }}">
@endif
@if($nextPageUrl)
<link rel="next" href="{{ $nextPageUrl }}">
@endif
<script type="application/ld+json">{!! json_encode($listingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@push('scripts')
<script>
const journalOverflows = (element) => element.scrollHeight > element.clientHeight + 1;

const fitJournalText = (box, text, dots, full, alwaysShowDots = false) => {
  text.textContent = full;
  dots.hidden = !alwaysShowDots;
  if (!journalOverflows(box)) {
    return;
  }

  dots.hidden = false;
  const words = full.split(/\s+/).filter((word) => word !== '');
  let low = 0;
  let high = words.length;
  let best = 0;

  while (low <= high) {
    const mid = Math.floor((low + high) / 2);
    text.textContent = words.slice(0, mid).join(' ');
    if (!journalOverflows(box)) {
      best = mid;
      low = mid + 1;
    } else {
      high = mid - 1;
    }
  }

  text.textContent = words.slice(0, best).join(' ').replace(/[\s,;:–—-]+$/u, '');
};

const clampJournalCards = () => {
  document.querySelectorAll('.journal-card-title').forEach((title) => {
    const text = title.querySelector('.journal-card-title-text');
    const dots = title.querySelector('.journal-card-ellipsis');
    if (!text || !dots) {
      return;
    }
    if (!text.dataset.full) {
      text.dataset.full = text.textContent;
    }
    fitJournalText(title, text, dots, text.dataset.full);
  });

  document.querySelectorAll('.journal-card-excerpt').forEach((excerpt) => {
    const text = excerpt.querySelector('.journal-card-excerpt-text');
    const dots = excerpt.querySelector('.journal-card-read-ellipsis');
    if (!text || !dots) {
      return;
    }
    if (!text.dataset.full) {
      text.dataset.full = text.textContent;
    }
    fitJournalText(excerpt, text, dots, text.dataset.full, true);
  });
};

clampJournalCards();
window.addEventListener('resize', clampJournalCards);
</script>
@endpush
