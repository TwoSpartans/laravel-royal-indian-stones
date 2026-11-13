@props([
  'topics',
  'topic' => null,
  'search' => '',
])

@php
  $searchFilter = $search !== '' ? ['q' => $search] : [];
  $journalUrl = function (array $params = []) use ($searchFilter): string {
      $query = array_filter([...$searchFilter, ...$params], fn (mixed $value): bool => $value !== null && $value !== '');

      return url('/blog').($query === [] ? '' : '?'.http_build_query($query)).'#stories';
  };
  $allStoriesUrl = $journalUrl();
@endphp

<div class="jn:flex jn:flex-col jn:gap-6 jn:lg:flex-row jn:lg:items-center jn:lg:justify-between jn:lg:gap-12">
  <nav aria-label="Journal topics">
    <ul class="jn:flex jn:flex-wrap jn:gap-x-6 jn:gap-y-1 jn:xl:gap-x-8">
      <li>
        <x-journal.topic-link :href="$allStoriesUrl" :current="$topic === null">All stories</x-journal.topic-link>
      </li>
      @foreach ($topics as $item)
        <li>
          <x-journal.topic-link
            :href="$journalUrl(['category' => $item->slug])"
            :current="$topic?->is($item) ?? false"
          >{{ $item->name }}</x-journal.topic-link>
        </li>
      @endforeach
    </ul>
  </nav>

  <form
    role="search"
    method="get"
    action="{{ url('/blog') }}#stories"
    class="jn:flex jn:w-full jn:items-center jn:border jn:border-journal-field jn:bg-journal-ivory jn:focus-within:outline-2 jn:focus-within:outline-offset-2 jn:focus-within:outline-journal-maroon jn:sm:max-w-[19rem] jn:lg:shrink-0"
  >
    @if ($topic)
      <input type="hidden" name="category" value="{{ $topic->slug }}">
    @endif
    <button type="submit" class="jn:flex jn:size-11 jn:shrink-0 jn:items-center jn:justify-center jn:focus-visible:outline-none">
      <img src="{{ asset('assets/img/journal/handoff/icon-search.svg') }}" width="18" height="18" alt="">
      <span class="jn:sr-only">Search</span>
    </button>
    <label for="journal-search" class="jn:sr-only">{{ $topic ? 'Search stories in '.$topic->name : 'Search stories' }}</label>
    <input
      id="journal-search"
      type="search"
      name="q"
      value="{{ $search }}"
      maxlength="100"
      placeholder="Search stories"
      enterkeyhint="search"
      autocomplete="off"
      class="jn:min-w-0 jn:flex-1 jn:bg-transparent jn:py-3 jn:pr-3 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-ink jn:placeholder:text-journal-muted jn:focus:outline-none"
    >
  </form>
</div>
