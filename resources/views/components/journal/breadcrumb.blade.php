@props([
  'items' => [],
])

<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-rih-wheat']) }}>
  <ol class="jn:flex jn:flex-wrap jn:items-center jn:gap-x-3 jn:gap-y-1">
    @foreach ($items as $item)
      <li class="jn:flex jn:items-center jn:gap-3">
        @unless ($loop->first)
          <span aria-hidden="true">/</span>
        @endunless
        @if ($loop->last || empty($item['url']))
          <span aria-current="page">{{ $item['name'] }}</span>
        @else
          <a
            href="{{ $item['url'] }}"
            class="jn:inline-flex jn:min-h-6 jn:items-center jn:underline-offset-4 jn:hover:text-journal-ivory jn:hover:underline jn:focus-visible:outline-2 jn:focus-visible:outline-offset-2 jn:focus-visible:outline-journal-ivory"
          >{{ $item['name'] }}</a>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
