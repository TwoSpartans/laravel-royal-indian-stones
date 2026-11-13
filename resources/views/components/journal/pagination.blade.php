@props([
  'pagination',
])

@php
  $pad = fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT);
  $squareClass = 'jn:inline-flex jn:size-11 jn:items-center jn:justify-center jn:text-sm jn:font-medium jn:leading-5';
  $linkFocus = 'jn:focus-visible:outline-2 jn:focus-visible:outline-offset-2 jn:focus-visible:outline-journal-maroon';
@endphp

<nav aria-label="Stories pagination" {{ $attributes->merge(['class' => 'jn:flex jn:justify-center']) }}>
  <ul class="jn:flex jn:flex-wrap jn:items-center jn:justify-center jn:gap-x-4 jn:gap-y-3">
    @if ($pagination['previous'])
      <li>
        <a href="{{ $pagination['previous'] }}" rel="prev" class="jn:inline-flex jn:min-h-11 jn:items-center jn:gap-2 jn:px-1 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-maroon jn:hover:underline jn:underline-offset-4 {{ $linkFocus }}">
          <span aria-hidden="true">←</span> Previous
        </a>
      </li>
    @endif

    @foreach ($pagination['pages'] as $item)
      <li>
        @if ($item['number'] === null)
          <span class="{{ $squareClass }} jn:text-journal-muted" aria-hidden="true">…</span>
        @elseif ($item['current'])
          <span class="{{ $squareClass }} jn:bg-journal-maroon jn:text-journal-ivory" aria-current="page">
            <span class="jn:sr-only">Page </span>{{ $pad($item['number']) }}
          </span>
        @else
          <a href="{{ $item['url'] }}" class="{{ $squareClass }} jn:border jn:border-journal-line jn:text-journal-indigo jn:transition-colors jn:hover:border-journal-maroon jn:hover:text-journal-maroon jn:motion-reduce:transition-none {{ $linkFocus }}">
            <span class="jn:sr-only">Page </span>{{ $pad($item['number']) }}
          </a>
        @endif
      </li>
    @endforeach

    @if ($pagination['next'])
      <li>
        <a href="{{ $pagination['next'] }}" rel="next" class="jn:inline-flex jn:min-h-11 jn:items-center jn:gap-2 jn:px-1 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-maroon jn:hover:underline jn:underline-offset-4 {{ $linkFocus }}">
          Next <span aria-hidden="true">→</span>
        </a>
      </li>
    @endif
  </ul>
</nav>
