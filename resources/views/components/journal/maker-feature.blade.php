@props([
  'maker',
])

<section aria-labelledby="journal-maker-title" class="jn:bg-journal-green jn:text-journal-ivory">
  <x-journal.container class="jn:grid jn:items-center jn:gap-8 jn:py-10 jn:lg:grid-cols-2 jn:lg:gap-16 jn:lg:py-16">
    <div class="jn:aspect-[342/260] jn:overflow-hidden jn:bg-journal-green jn:md:aspect-[608/420]">
      <x-journal.picture
        :src="$maker['image']['src']"
        :alt="$maker['image']['alt']"
        :width="$maker['image']['width']"
        :height="$maker['image']['height']"
        :avif-srcset="$maker['image']['avifSrcset']"
        :webp-srcset="$maker['image']['srcset']"
        sizes="(min-width: 1440px) 608px, (min-width: 1024px) calc((100vw - 9rem) / 2), calc(100vw - 3rem)"
        class="jn:size-full jn:object-cover"
      />
    </div>

    <div class="jn:flex jn:flex-col jn:items-start jn:gap-6">
      <img src="{{ asset('assets/img/journal/handoff/ornament.svg') }}" width="40" height="40" alt="">

      <p class="jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-rih-wheat">The hands behind the craft</p>

      <h2 id="journal-maker-title" class="jn:font-brand jn:text-journal-ivory jn:text-[2.75rem] jn:font-medium jn:leading-[1.04] jn:text-balance jn:min-[375px]:text-[3.25rem] jn:lg:max-w-[25rem] jn:lg:text-[3.125rem]">
        {{ $maker['heading'] }}
      </h2>

      <p class="jn:max-w-[38rem] jn:text-base jn:leading-6 jn:text-journal-ivory/90">{{ $maker['text'] }}</p>

      <a
        href="{{ $maker['url'] }}"
        class="jn:inline-flex jn:min-h-13 jn:items-center jn:justify-center jn:gap-2 jn:border jn:border-journal-gold jn:px-8 jn:py-4 jn:text-sm jn:font-medium jn:leading-5 jn:transition-colors jn:hover:bg-journal-gold/15 jn:focus-visible:outline-2 jn:focus-visible:outline-offset-4 jn:focus-visible:outline-journal-ivory jn:motion-reduce:transition-none"
      >
        {{ $maker['buttonLabel'] }}
        <span aria-hidden="true">↗</span>
      </a>
    </div>
  </x-journal.container>
</section>
