@props([
  'heading',
  'eyebrow' => 'The Royal Journal',
  'intro' => null,
  'breadcrumbs' => [],
])

<section aria-labelledby="journal-title" class="jn:bg-journal-green jn:text-journal-ivory">
  <x-journal.container class="jn:flex jn:flex-col jn:gap-6 jn:pt-8 jn:pb-6 jn:lg:gap-8 jn:lg:pt-12 jn:lg:pb-8">
    <x-journal.breadcrumb :items="$breadcrumbs" />

    <div class="jn:flex jn:max-w-[50rem] jn:flex-col jn:items-start jn:gap-4">
      <p class="jn:flex jn:items-center jn:gap-3 jn:text-xs jn:font-medium jn:uppercase jn:leading-4 jn:tracking-[0.14em] jn:text-rih-wheat">
        <img src="{{ asset('assets/img/journal/handoff/ornament-small.svg') }}" width="26" height="26" alt="" class="jn:shrink-0">
        {{ $eyebrow }}
      </p>

      <h1 id="journal-title" class="jn:font-brand jn:text-journal-ivory jn:text-[clamp(2.5rem,2.2rem+2vw,4rem)] jn:font-medium jn:leading-[1.05] jn:[overflow-wrap:anywhere]">
        {{ $heading }}
      </h1>

      @if (filled($intro))
        <p class="jn:text-base jn:leading-6 jn:text-journal-ivory/90">{{ $intro }}</p>
      @endif
    </div>

    <x-journal.floral-border />
  </x-journal.container>
</section>
