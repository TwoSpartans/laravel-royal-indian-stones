@props([
  'cover',
  'breadcrumbs' => [],
])

<section aria-labelledby="journal-title" class="jn:bg-journal-green jn:text-journal-ivory">
  <x-journal.container class="jn:flex jn:flex-col jn:gap-6 jn:pt-8 jn:pb-6 jn:lg:gap-10 jn:lg:pt-12 jn:lg:pb-8">
    <x-journal.breadcrumb :items="$breadcrumbs" />

    <div class="jn:grid jn:items-center jn:gap-8 jn:lg:grid-cols-[540fr_676fr] jn:lg:gap-16">
      <div class="jn:flex jn:flex-col jn:items-start jn:gap-6">
        <p class="jn:flex jn:items-center jn:gap-3 jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-rih-wheat">
          <img src="{{ asset('assets/img/journal/handoff/ornament-small.svg') }}" width="26" height="26" alt="" class="jn:shrink-0">
          The Royal Journal
        </p>

        <h1 id="journal-title" class="jn:font-brand jn:text-[clamp(2.75rem,2.5rem+3.05vw,5.25rem)] jn:font-medium jn:leading-[1.03] jn:text-journal-ivory">
          <span class="jn:block">Stories with</span>
          <em class="jn:block jn:font-normal">an Indian soul.</em>
        </h1>

        <p class="jn:max-w-[33.75rem] jn:text-base jn:leading-6 jn:text-journal-ivory/90">
          A celebration of Indian craftsmanship, thoughtful homes and the beauty of things made by hand.
        </p>

        <a
          href="#stories"
          class="jn:inline-flex jn:min-h-13 jn:items-center jn:justify-center jn:gap-2 jn:bg-journal-gold jn:px-8 jn:py-4 jn:text-sm jn:font-medium jn:leading-5 jn:text-journal-indigo jn:transition-colors jn:hover:bg-rih-wheat jn:focus-visible:outline-2 jn:focus-visible:outline-offset-4 jn:focus-visible:outline-journal-ivory jn:motion-reduce:transition-none"
        >
          Discover the stories
          <span aria-hidden="true">↗</span>
        </a>

        <p class="jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-rih-wheat">
          Craft <span aria-hidden="true">&nbsp;·&nbsp;</span> Culture <span aria-hidden="true">&nbsp;·&nbsp;</span> Considered living
        </p>
      </div>

      <figure class="jn:flex jn:flex-col jn:gap-3">
        <div class="jn:aspect-[342/370] jn:overflow-hidden jn:rounded-t-full jn:rounded-b-[2px] jn:border jn:border-journal-gold jn:bg-journal-green jn:md:aspect-[676/560]">
          <x-journal.picture
            :src="$cover['src']"
            :alt="$cover['alt']"
            :width="$cover['width']"
            :height="$cover['height']"
            :avif-srcset="$cover['avifSrcset']"
            :webp-srcset="$cover['srcset']"
            sizes="(min-width: 1440px) 676px, (min-width: 1024px) calc((100vw - 9rem) * 0.556), calc(100vw - 3rem)"
            loading="eager"
            fetchpriority="high"
            class="jn:size-full jn:object-cover"
          />
        </div>
        @if (filled($cover['caption']))
          <figcaption class="jn:text-xs jn:leading-4 jn:tracking-[0.04em] jn:text-journal-ivory/80">{{ $cover['caption'] }}</figcaption>
        @endif
      </figure>
    </div>

    <x-journal.floral-border />
  </x-journal.container>
</section>
