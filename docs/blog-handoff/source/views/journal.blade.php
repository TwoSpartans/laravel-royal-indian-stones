@extends('layouts.site')

@section('content')
<main id="main" class="journal">
  <section class="journal-intro-band" aria-labelledby="journal-title">
    <div class="container">
      <div class="journal-intro">
        <div class="journal-intro-title">
          <p class="journal-eyebrow">{{ $copy['eyebrow'] }}</p>
          <h1 id="journal-title">{{ $copy['heading_lead'] }} <em>{{ $copy['heading_emphasis'] }}</em></h1>
        </div>
        <p class="journal-lede">{{ $copy['introduction'] }}</p>
      </div>
    </div>
  </section>

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
          @if(trim((string) $featured->summary) !== '')
            <p class="journal-feature-summary">{{ $featured->summary }}</p>
          @endif
          <p class="journal-feature-meta">{{ $featured->readingMinutes() }} min read</p>
          <span class="journal-button">Read the story<span class="journal-arrow" aria-hidden="true">↗</span></span>
        </div>
      </a>
    </article>
  @endif

  <section class="journal-browse" id="journal-stories" aria-labelledby="journal-browse-title">
    <div class="container">
      @if($mode === 'empty')
        <div class="journal-empty">
          <h2 id="journal-browse-title">Stories are on the way.</h2>
          <p>There are no published stories yet. The workshop and the stone collection are open while the journal grows.</p>
          <div class="journal-empty-links">
            <a href="{{ url('/products') }}">Browse the stones</a>
            <a href="{{ url('/aboutus') }}">About the workshop</a>
          </div>
        </div>
      @else
        <div class="journal-browse-head">
          <h2 id="journal-browse-title">
            @if($search !== '')
              Stories matching “{{ $search }}”
            @elseif($category)
              {{ $category->name }}
            @else
              Latest stories
            @endif
          </h2>
          <nav class="journal-pills" aria-label="Topics">
            <a href="{{ url('/blog') }}" @if($category === null && $search === '') aria-current="page" @endif>All stories</a>
            @foreach($topics as $topic)
              <a href="{{ url('/blog').'?'.http_build_query(['category' => $topic->slug]) }}" @if($category && $category->is($topic)) aria-current="page" @endif>{{ $topic->name }}</a>
            @endforeach
          </nav>
          <form class="journal-search" method="get" action="{{ url('/blog') }}" role="search">
            @if($category)
              <input type="hidden" name="category" value="{{ $category->slug }}">
            @endif
            <label class="visually-hidden" for="journal-search">Search the journal</label>
            <div class="journal-search-field">
              <img src="{{ asset('assets/img/journal/design/search.svg') }}" alt="" width="18" height="18">
              <input id="journal-search" type="search" name="q" value="{{ $search }}" placeholder="Search the journal" maxlength="120" enterkeyhint="search">
              <button type="submit">Search</button>
            </div>
          </form>
        </div>

        @if($resetUrl && ($search !== '' || $category))
          <p class="journal-reset"><a href="{{ $resetUrl }}">{{ $resetLabel }}</a></p>
        @endif
        @if($resultSummary)
          <p class="journal-count">{{ $resultSummary }}</p>
        @endif

        @if($posts && $posts->isNotEmpty())
          <div class="journal-card-grid">
            @foreach($posts as $post)
              <x-journal-card :post="$post" />
            @endforeach
          </div>
          @if($pageNumbers !== [])
            <nav class="journal-pager" aria-label="Stories pagination">
              @if($posts->currentPage() > 1)
                <a href="{{ $posts->previousPageUrl() }}" rel="prev">Previous</a>
              @endif
              @foreach($pageNumbers as $pageNumber)
                @if($pageNumber === $posts->currentPage())
                  <span aria-current="page">{{ $pageNumber }}</span>
                @else
                  <a href="{{ $posts->url($pageNumber) }}" aria-label="Page {{ $pageNumber }}">{{ $pageNumber }}</a>
                @endif
              @endforeach
              @if($posts->hasMorePages())
                <a class="journal-pager-next" href="{{ $posts->nextPageUrl() }}" rel="next">Next <span class="journal-arrow" aria-hidden="true">→</span></a>
              @endif
            </nav>
          @endif
        @elseif($search !== '' || $category)
          <div class="journal-empty">
            @if($search !== '')
              <p>No stories match “{{ $search }}”. Try another word, or look through the published stories.</p>
            @else
              <p>No stories in this topic yet.</p>
            @endif
            @if($resetUrl)
              <div class="journal-empty-links">
                <a href="{{ $resetUrl }}">{{ $resetLabel }}</a>
              </div>
            @endif
          </div>
        @endif
      @endif
    </div>
  </section>
</main>
@endsection

@push('head')
<link href="{{ asset('assets/css/journal.css') }}?v=10" rel="stylesheet">
@if($posts && $posts->previousPageUrl())
<link rel="prev" href="{{ $posts->previousPageUrl() }}">
@endif
@if($posts && $posts->nextPageUrl())
<link rel="next" href="{{ $posts->nextPageUrl() }}">
@endif
<script type="application/ld+json">{!! json_encode($listingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
