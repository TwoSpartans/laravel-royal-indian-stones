@extends('layouts.site')

@section('content')
@php
    $isSearching = $search !== '';
    $total = $stories?->total() ?? 0;
    $storyWord = fn (int $count): string => $count === 1 ? 'story' : 'stories';
    $archiveNumber = $closerLookCards !== [] ? '03' : '02';
    $primaryButton = 'jn:inline-flex jn:min-h-12 jn:items-center jn:justify-center jn:gap-2 jn:bg-journal-maroon jn:px-6 jn:py-3 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-ivory jn:transition-colors jn:hover:bg-journal-indigo jn:focus-visible:outline-2 jn:focus-visible:outline-offset-4 jn:focus-visible:outline-journal-maroon jn:motion-reduce:transition-none';
    $textLink = 'jn:inline-flex jn:min-h-11 jn:items-center jn:gap-2 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-maroon jn:underline-offset-4 jn:hover:underline jn:focus-visible:outline-2 jn:focus-visible:outline-offset-2 jn:focus-visible:outline-journal-maroon';
    $rangeLabel = $total > 0 ? 'Showing '.$stories->firstItem().'–'.$stories->lastItem().' of '.$total : '';
@endphp
<main id="main">
    <div id="journal" class="jn:bg-journal-ivory jn:text-journal-ink jn:font-sans">
        @if ($isLanding)
            <x-journal.cover :cover="$cover" :breadcrumbs="$breadcrumbs" />
        @elseif ($isSearching)
            <x-journal.masthead
                eyebrow="Search the Journal"
                :heading="'Results for “'.$search.'”'"
                :intro="$topic ? 'Searching stories in '.$topic->name.'.' : null"
                :breadcrumbs="$breadcrumbs"
            />
        @elseif ($topic)
            <x-journal.masthead
                eyebrow="Journal topic"
                :heading="$topic->name"
                :intro="$topic->description ?? null"
                :breadcrumbs="$breadcrumbs"
            />
        @else
            <x-journal.masthead
                heading="More from the Journal"
                :intro="$stories ? 'Page '.$stories->currentPage().' of '.$stories->lastPage().' of our earlier stories.' : null"
                :breadcrumbs="$breadcrumbs"
            />
        @endif

        @if ($isLanding && ! $hasStories)
            <section id="stories" aria-labelledby="journal-empty-title" class="jn:scroll-mt-32 jn:lg:scroll-mt-36">
                <x-journal.container class="jn:py-10 jn:lg:py-16">
                    <x-journal.empty-state title="Stories are on the way" heading-id="journal-empty-title">
                        <p>The journal is on the way. We will publish stories about sandstone, marble, granite, and the other stones we cut in Jodhpur. Until the first articles go live, browse the collection or read about the workshop.</p>
                        <x-slot:actions>
                            <a href="{{ url('/products') }}" class="{{ $primaryButton }}">Browse the stones</a>
                            <a href="{{ url('/aboutus') }}" class="{{ $textLink }}">About the workshop <span aria-hidden="true">↗</span></a>
                            <a href="{{ route('contact') }}" class="{{ $textLink }}">Talk to us</a>
                        </x-slot:actions>
                    </x-journal.empty-state>
                </x-journal.container>
            </section>
        @elseif ($isLanding)
            <section id="stories" aria-labelledby="journal-edit-title" class="jn:scroll-mt-32 jn:lg:scroll-mt-36">
                <x-journal.container class="jn:flex jn:flex-col jn:gap-8 jn:pt-8 jn:pb-10 jn:lg:gap-10 jn:lg:pt-12 jn:lg:pb-16">
                    <x-journal.browse-bar :topics="$topics" />
                    <hr class="jn:border-journal-line">

                    <x-journal.section-heading eyebrow="01 / The edit" title="For a beautifully lived-in home." id="journal-edit-title">
                        @if ($storyCards !== [])
                            <x-slot:action>
                                <a href="#archive" class="{{ $textLink }}">View all stories <span aria-hidden="true">↗</span></a>
                            </x-slot:action>
                        @endif
                    </x-journal.section-heading>

                    <div class="journal-card-grid jn:grid jn:grid-cols-1 jn:gap-x-6 jn:gap-y-12 jn:sm:grid-cols-2 jn:lg:grid-cols-3">
                        @foreach ($editCards as $card)
                            <x-journal.article-card :card="$card" />
                        @endforeach
                    </div>
                </x-journal.container>
            </section>

            @if ($maker)
                <x-journal.maker-feature :maker="$maker" />
            @endif

            @if ($closerLookCards !== [])
                <section aria-labelledby="journal-closer-title">
                    <x-journal.container class="jn:flex jn:flex-col jn:gap-8 jn:py-10 jn:lg:gap-10 jn:lg:py-16">
                        <x-journal.section-heading eyebrow="02 / A closer look" title="Know the materials. Love the details." id="journal-closer-title" />
                        <div class="jn:grid jn:grid-cols-1 jn:gap-8 jn:md:grid-cols-2">
                            @foreach ($closerLookCards as $card)
                                <x-journal.article-card :card="$card" size="large" />
                            @endforeach
                        </div>
                    </x-journal.container>
                </section>
            @endif

            @if ($storyCards !== [])
                <section id="archive" aria-labelledby="journal-archive-title" class="jn:scroll-mt-32 jn:border-t jn:border-journal-line jn:lg:scroll-mt-36">
                    <x-journal.container class="jn:flex jn:flex-col jn:gap-8 jn:py-10 jn:lg:gap-10 jn:lg:py-16">
                        <x-journal.section-heading :eyebrow="$archiveNumber.' / The archive'" title="More from the Journal." id="journal-archive-title">
                            <x-slot:action>
                                <p class="jn:text-sm jn:leading-5 jn:text-journal-muted">{{ $rangeLabel }} {{ $storyWord($total) }}</p>
                            </x-slot:action>
                        </x-journal.section-heading>
                        <div class="journal-card-grid jn:grid jn:grid-cols-1 jn:gap-x-6 jn:gap-y-12 jn:sm:grid-cols-2 jn:lg:grid-cols-3">
                            @foreach ($storyCards as $card)
                                <x-journal.article-card :card="$card" />
                            @endforeach
                        </div>
                        @if ($pagination)
                            <x-journal.pagination :pagination="$pagination" class="jn:pt-4" />
                        @endif
                    </x-journal.container>
                </section>
            @endif
        @else
            <section id="stories" aria-labelledby="journal-results-title" class="jn:scroll-mt-32 jn:lg:scroll-mt-36">
                <x-journal.container class="jn:flex jn:flex-col jn:gap-8 jn:pt-8 jn:pb-10 jn:lg:gap-10 jn:lg:pt-12 jn:lg:pb-16">
                    @if ($hasStories)
                        <x-journal.browse-bar :topics="$topics" :topic="$topic" :search="$search" />
                        <hr class="jn:border-journal-line">
                    @endif

                    @if ($total > 0)
                        <div class="jn:flex jn:flex-col jn:gap-3 jn:sm:flex-row jn:sm:items-end jn:sm:justify-between jn:sm:gap-8">
                            <div class="jn:flex jn:flex-col jn:gap-1">
                                <h2 id="journal-results-title" class="jn:font-brand jn:text-[2rem] jn:font-medium jn:leading-9 jn:text-journal-indigo jn:[overflow-wrap:anywhere]">
                                    @if ($isSearching)
                                        {{ $total }} {{ $storyWord($total) }} found{{ $topic ? ' in '.$topic->name : '' }}
                                    @elseif ($topic)
                                        {{ $total }} {{ $storyWord($total) }} in {{ $topic->name }}
                                    @else
                                        Earlier stories
                                    @endif
                                </h2>
                                @if ($stories->lastPage() > 1)
                                    <p class="jn:text-sm jn:leading-5 jn:text-journal-muted">{{ $rangeLabel }}</p>
                                @endif
                            </div>
                            @if ($isSearching || $topic)
                                <div class="jn:flex jn:flex-wrap jn:gap-x-6">
                                    @if ($isSearching)
                                        <a href="{{ $topic ? url('/blog').'?'.http_build_query(['category' => $topic->slug]) : url('/blog') }}#stories" class="{{ $textLink }}">Clear search</a>
                                    @endif
                                    <a href="{{ url('/blog') }}" class="{{ $textLink }}">Back to the Journal</a>
                                </div>
                            @endif
                        </div>
                        <div class="journal-card-grid jn:grid jn:grid-cols-1 jn:gap-x-6 jn:gap-y-12 jn:sm:grid-cols-2 jn:lg:grid-cols-3">
                            @foreach ($storyCards as $card)
                                <x-journal.article-card :card="$card" />
                            @endforeach
                        </div>
                        @if ($pagination)
                            <x-journal.pagination :pagination="$pagination" class="jn:pt-4" />
                        @endif
                    @elseif ($isSearching)
                        <x-journal.empty-state :title="'No stories match “'.$search.'”'" heading-id="journal-results-title">
                            <p>Try a shorter or different word, such as a stone, a finish, or a room{{ $topic ? ', or search every topic instead of '.$topic->name : '' }}.</p>
                            <x-slot:actions>
                                <a href="{{ url('/blog') }}" class="{{ $primaryButton }}">Browse all stories</a>
                                @if ($topic)
                                    <a href="{{ url('/blog').'?'.http_build_query(['q' => $search]) }}#stories" class="{{ $textLink }}">Search all topics</a>
                                @endif
                            </x-slot:actions>
                        </x-journal.empty-state>
                    @else
                        <x-journal.empty-state :title="$topic ? 'No stories in '.$topic->name.' yet' : 'No stories here yet'" heading-id="journal-results-title">
                            <p>We have not published in this topic yet. Browse the full Journal, or look through the stones our stories are written about.</p>
                            <x-slot:actions>
                                <a href="{{ url('/blog') }}" class="{{ $primaryButton }}">Browse all stories</a>
                                <a href="{{ url('/products') }}" class="{{ $textLink }}">Browse the stones</a>
                            </x-slot:actions>
                        </x-journal.empty-state>
                    @endif
                </x-journal.container>
            </section>
        @endif
    </div>
</main>
@endsection

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/journal.css'])
@if ($isLanding && filled($cover['avifSrcset'] ?? null))
<link rel="preload" as="image" type="image/avif" imagesrcset="{{ $cover['avifSrcset'] }}" imagesizes="(min-width: 1440px) 676px, (min-width: 1024px) calc((100vw - 9rem) * 0.556), calc(100vw - 3rem)" fetchpriority="high">
@endif
@if ($previousPageUrl)
<link rel="prev" href="{{ $previousPageUrl }}">
@endif
@if ($nextPageUrl)
<link rel="next" href="{{ $nextPageUrl }}">
@endif
<script type="application/ld+json">{!! json_encode($listingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
