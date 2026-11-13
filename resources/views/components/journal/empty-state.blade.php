@props([
  'title',
  'headingId',
  'headingLevel' => 'h2',
])

<div {{ $attributes->merge(['class' => 'jn:flex jn:flex-col jn:items-start jn:gap-5 jn:border jn:border-journal-line jn:px-6 jn:py-10 jn:sm:px-10 jn:lg:py-14']) }}>
  <img src="{{ asset('assets/img/journal/handoff/ornament.svg') }}" width="40" height="40" alt="">
  <{{ $headingLevel }} id="{{ $headingId }}" class="jn:font-brand jn:text-[2.25rem] jn:font-medium jn:leading-[1.1] jn:text-journal-indigo jn:text-balance jn:sm:text-[2.75rem] jn:[overflow-wrap:anywhere]">{{ $title }}</{{ $headingLevel }}>
  <div class="jn:max-w-[40rem] jn:text-base jn:leading-6 jn:text-journal-muted">{{ $slot }}</div>
  @isset($actions)
    <div class="jn:flex jn:flex-wrap jn:items-center jn:gap-x-6 jn:gap-y-3">{{ $actions }}</div>
  @endisset
</div>
