@props([
  'card',
  'size' => 'standard',
  'headingLevel' => 'h3',
])

@php
  $isLarge = $size === 'large';
  $mediaClass = $isLarge ? 'jn:aspect-[342/250] jn:lg:aspect-[624/250]' : 'jn:aspect-[342/250] jn:sm:aspect-[410/250]';
  $sizes = $isLarge
    ? '(min-width: 1440px) 624px, (min-width: 768px) calc((100vw - 7rem) / 2), calc(100vw - 3rem)'
    : '(min-width: 1440px) 411px, (min-width: 1024px) calc((100vw - 8rem) / 3), (min-width: 640px) calc((100vw - 5.5rem) / 2), calc(100vw - 3rem)';
@endphp

<article {{ $attributes->merge(['class' => 'jn:group jn:relative jn:flex jn:flex-col jn:has-[a:focus-visible]:outline-2 jn:has-[a:focus-visible]:outline-offset-4 jn:has-[a:focus-visible]:outline-journal-maroon']) }}>
  <div class="journal-card-media {{ $mediaClass }} jn:overflow-hidden jn:bg-journal-line/45">
    @if ($card['image'])
      <x-journal.picture
        :src="$card['image']['src']"
        :alt="$card['image']['alt']"
        :width="$card['image']['width']"
        :height="$card['image']['height']"
        :avif-srcset="$card['image']['avifSrcset']"
        :webp-srcset="$card['image']['srcset']"
        :sizes="$sizes"
        class="jn:size-full jn:object-cover jn:transition-transform jn:duration-500 jn:ease-out jn:group-hover:scale-[1.02] jn:motion-reduce:transition-none jn:motion-reduce:group-hover:scale-100"
      />
    @else
      <div class="jn:flex jn:size-full jn:items-center jn:justify-center">
        <img src="{{ asset('assets/img/journal/handoff/ornament.svg') }}" width="40" height="40" alt="" class="jn:opacity-70">
      </div>
    @endif
  </div>

  <div class="jn:flex jn:flex-1 jn:flex-col jn:gap-3 jn:pt-6">
    @if (filled($card['category']))
      <p class="jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.1em] jn:text-journal-maroon">{{ $card['category'] }}</p>
    @endif

    <{{ $headingLevel }} class="jn:font-brand jn:text-[2rem] jn:font-medium jn:leading-9 jn:text-journal-ink jn:[overflow-wrap:anywhere]">
      <a
        href="{{ $card['url'] }}"
        class="jn:decoration-1 jn:underline-offset-[6px] jn:after:absolute jn:after:inset-0 jn:group-hover:underline jn:focus-visible:outline-none"
      >{{ $card['title'] }}</a>
    </{{ $headingLevel }}>

    @if (filled($card['excerpt']))
      <p class="jn:line-clamp-4 jn:text-base jn:leading-6 jn:text-journal-muted">{{ $card['excerpt'] }}</p>
    @endif

    <p class="jn:flex jn:flex-wrap jn:items-center jn:gap-x-3 jn:gap-y-1 jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.04em] jn:text-journal-muted">
      <time datetime="{{ $card['publishedAt'] }}">{{ $card['publishedLabel'] }}</time>
      @if ($card['readTime'])
        <span aria-hidden="true">·</span>
        <span>{{ $card['readTime'] }} min read</span>
      @endif
    </p>

    <p class="jn:mt-auto jn:pt-1 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-maroon" aria-hidden="true">
      Read story <span class="jn:ml-1">↗</span>
    </p>
  </div>
</article>
